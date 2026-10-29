/*
 * ======================================================================================
 * IoTR // NEXUS — FIRMWARE ESP32 UNIT INDOOR (GAS MECHATRONICS, DHT22, TFT & BUZZER D4)
 * ======================================================================================
 * Identitas Pabrikan : IOTR-INDOOR-01
 * Password Klaim     : INDOOR123
 * API Token Internal : IOTR_SECRET_TOKEN_INDOOR_ESP32_112233
 * Pin Mapping        : MQ-2 (D35), DHT22 (D15), Servo Gas (D13), Buzzer (D4), TFT ILI9341
 * Arsitektur         : Hybrid Dual-Protocol (ESP-NOW Instant RX + Wi-Fi HTTP POST Cloud)
 * ======================================================================================
 */

#include <WiFi.h>
#include <esp_now.h>
#include <HTTPClient.h>
#include <ESP32Servo.h>
#include <DHT.h>
#include <SPI.h>
#include <Adafruit_GFX.h>
#include <Adafruit_ILI9341.h>

// ======================== 1. KONFIGURASI JARINGAN & CLOUD API ========================
const char* WIFI_SSID     = "NAMA_WIFI_ANDA";
const char* WIFI_PASSWORD = "PASSWORD_WIFI_ANDA";
const char* SERVER_URL    = "http://192.168.1.100:8000/api/telemetry"; // Ganti dengan IP/Domain Laravel Anda
const char* API_TOKEN     = "IOTR_SECRET_TOKEN_INDOOR_ESP32_112233";

// ======================== 2. PEMETAAN PIN HARDWARE INDOOR ============================
#define PIN_MQ2_GAS      35  // ADC1_CH7 Sensor Gas MQ-2 (0 - 4095)
#define PIN_DHT22        15  // Digital Input Sensor Suhu & Kelembapan DHT22
#define PIN_SERVO_GAS    13  // PWM Jarum Speedometer Analog Gas (20°, 90°, 160°)
#define PIN_BUZZER       4   // Pin D4 Audio Buzzer Aktif/Pasif (Bel & Sirine Darurat)

// Pin SPI Layar Warna TFT ILI9341 (240x320)
#define TFT_CS           5
#define TFT_DC           2
#define TFT_RST          16

#define DHTTYPE          DHT22

// ======================== 3. AMBANG BATAS MEKATRONIKA GAS MQ-2 =======================
#define GAS_WASPADA_THRESHOLD 1500
#define GAS_BAHAYA_THRESHOLD  3000

#define SUDUT_ZONA_HIJAU      20   // Aman (< 1500)
#define SUDUT_ZONA_KUNING     90   // Waspada (1500 - 2999)
#define SUDUT_ZONA_MERAH      160  // Bahaya (>= 3000)

// ======================== 4. OBJEK SENSOR, SERVO & LAYAR TFT =========================
DHT dht(PIN_DHT22, DHTTYPE);
Servo gasGaugeServo;
Adafruit_ILI9341 tft = Adafruit_ILI9341(TFT_CS, TFT_DC, TFT_RST);

// Struktur Paket Data dari ESP32 Outdoor via ESP-NOW
typedef struct struct_espnow_packet {
    int status_bel;
    int sudut_solar;
    int cahaya_timur;
    int cahaya_barat;
} struct_espnow_packet;

volatile int statusBelDariOutdoor = 0;
volatile bool adaPanggilanBelBaru = false;
unsigned long waktuTerimaBel      = 0;

float suhuRuangan       = 28.0;
float kelembapanRuangan = 62.0;
int kadarGasAdc         = 650;
int sudutServoGas       = SUDUT_ZONA_HIJAU;

unsigned long lastSensorRead      = 0;
unsigned long lastCloudSync       = 0;
const unsigned long SENSOR_INTERVAL = 1000; // Baca sensor setiap 1 detik
const unsigned long CLOUD_INTERVAL  = 5000; // Kirim HTTP POST ke Laravel setiap 5 detik

// ======================== 5. FUNGSI AUDIO BUZZER PIN D4 ==============================
void bunyikanBelTamu() {
    // Pola Nada Ramah "TING-TUNG" (1x Klik Tamu Datang)
    tone(PIN_BUZZER, 1046, 280); // Nada C6
    delay(320);
    tone(PIN_BUZZER, 784, 450);  // Nada G5
    delay(500);
    noTone(PIN_BUZZER);
}

void bunyikanBelKurir() {
    // Pola Nada "BEEP 3x" Cepat (3x Klik Kurir Paket)
    for (int i = 0; i < 3; i++) {
        tone(PIN_BUZZER, 1318, 160); // Nada E6
        delay(220);
    }
    noTone(PIN_BUZZER);
}

void bunyikanSirineGasBocor() {
    // Pola Sirine Darurat Frekuensi Tinggi saat Gas >= 3000
    tone(PIN_BUZZER, 1800, 200);
    delay(200);
    tone(PIN_BUZZER, 1200, 200);
    delay(200);
}

// ======================== 6. FUNGSI RENDER UI LAYAR TFT ILI9341 ======================
void perbaruiLayarTft() {
    // Jika terjadi Kebocoran Gas Kritis (>= 3000), prioritaskan Layar Merah Darurat!
    if (kadarGasAdc >= GAS_BAHAYA_THRESHOLD) {
        tft.fillScreen(ILI9341_RED);
        tft.setTextColor(ILI9341_WHITE);
        tft.setTextSize(2);
        tft.setCursor(18, 35);
        tft.println("!! PERINGATAN DARURAT !!");
        tft.setTextSize(3);
        tft.setCursor(25, 85);
        tft.println("GAS BOCOR!");
        tft.setTextSize(2);
        tft.setCursor(18, 145);
        tft.printf("MQ-2 ADC : %d\n", kadarGasAdc);
        tft.setCursor(18, 175);
        tft.printf("SERVO    : %d DERAJAT\n", sudutServoGas);
        tft.setCursor(18, 205);
        tft.println("SIRINE D4: AKTIF!");
        return;
    }

    // Jika ada panggilan Bel dari Unit Outdoor
    if (statusBelDariOutdoor == 1) {
        tft.fillScreen(ILI9341_DARKGREEN);
        tft.setTextColor(ILI9341_WHITE);
        tft.setTextSize(2);
        tft.setCursor(20, 45);
        tft.println(" PANGGILAN GERBANG (1x) ");
        tft.setTextSize(3);
        tft.setCursor(35, 95);
        tft.println("TAMU DATANG");
        tft.setTextSize(2);
        tft.setCursor(30, 155);
        tft.println(">> SILAKAN MASUK <<");
        return;
    } else if (statusBelDariOutdoor == 2) {
        tft.fillScreen(ILI9341_ORANGE);
        tft.setTextColor(ILI9341_BLACK);
        tft.setTextSize(2);
        tft.setCursor(20, 45);
        tft.println(" PANGGILAN GERBANG (3x) ");
        tft.setTextSize(3);
        tft.setCursor(30, 95);
        tft.println("KURIR PAKET");
        tft.setTextSize(2);
        tft.setCursor(20, 155);
        tft.println(">> TARUH DI GERBANG <<");
        return;
    }

    // Tampilan Normal Dashboard TFT Cyber-Industrial
    tft.fillScreen(ILI9341_BLACK);
    tft.drawRect(4, 4, 312, 232, ILI9341_CYAN);

    tft.setTextColor(ILI9341_CYAN);
    tft.setTextSize(2);
    tft.setCursor(16, 16);
    tft.println("IoTR // INDOOR NEXUS");

    tft.setTextColor(ILI9341_WHITE);
    tft.setCursor(16, 55);
    tft.printf("Suhu Ruang : %.1f C\n", suhuRuangan);
    tft.setCursor(16, 85);
    tft.printf("Kelembapan : %.1f %%\n", kelembapanRuangan);
    tft.setCursor(16, 115);
    tft.printf("Gas MQ-2   : %d ADC\n", kadarGasAdc);
    tft.setCursor(16, 145);
    tft.printf("Jarum Servo: %d deg\n", sudutServoGas);

    tft.setCursor(16, 185);
    if (kadarGasAdc >= GAS_WASPADA_THRESHOLD) {
        tft.setTextColor(ILI9341_YELLOW);
        tft.println("STATUS: WASPADA GAS!");
    } else {
        tft.setTextColor(ILI9341_GREEN);
        tft.println("STATUS: AMAN & STABIL");
    }
}

// ======================== 7. CALLBACK PENERIMAAN INSTAN ESP-NOW ======================
void OnDataRecv(const uint8_t * mac, const uint8_t *incomingData, int len) {
    struct_espnow_packet packet;
    memcpy(&packet, incomingData, sizeof(packet));

    statusBelDariOutdoor = packet.status_bel;
    adaPanggilanBelBaru  = true;
    waktuTerimaBel       = millis();

    Serial.printf("[ESP-NOW RX] Sinyal Outdoor Diterima! Status Bel: %d | Solar: %d deg\n",
                  packet.status_bel, packet.sudut_solar);
}

// ======================== 8. FUNGSI KIRIM HTTP POST KE LARAVEL CLOUD =================
void kirimTelemetriIndoorKeLaravel() {
    if (WiFi.status() != WL_CONNECTED) {
        WiFi.reconnect();
        return;
    }

    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Accept", "application/json");
    http.addHeader("Authorization", String("Bearer ") + API_TOKEN);

    String jsonPayload = "{";
    jsonPayload += "\"suhu\":" + String(suhuRuangan, 1) + ",";
    jsonPayload += "\"kelembapan\":" + String(kelembapanRuangan, 1) + ",";
    jsonPayload += "\"kadar_gas\":" + String(kadarGasAdc) + ",";
    jsonPayload += "\"sudut_servo_gas\":" + String(sudutServoGas) + ",";
    jsonPayload += "\"status_bel\":" + String((int)statusBelDariOutdoor);
    jsonPayload += "}";

    int httpResponseCode = http.POST(jsonPayload);

    if (httpResponseCode > 0) {
        Serial.printf("[CLOUD HTTP POST] Kode: %d | Payload: %s\n", httpResponseCode, jsonPayload.c_str());
    } else {
        Serial.printf("[CLOUD ERROR] HTTP POST Gagal: %s\n", http.errorToString(httpResponseCode).c_str());
    }

    http.end();
}

// ======================== 9. SETUP AWAL MIKROKONTROLER INDOOR ========================
void setup() {
    Serial.begin(115200);
    delay(500);
    Serial.println("\n=======================================================");
    Serial.println("IoTR // NEXUS — INDOOR NODE (IOTR-INDOOR-01) BOOTING");
    Serial.println("=======================================================");

    pinMode(PIN_MQ2_GAS, INPUT);
    pinMode(PIN_BUZZER, OUTPUT);
    digitalWrite(PIN_BUZZER, LOW);

    dht.begin();

    // Inisialisasi Servo Analog Speedometer Gas (Pin D13)
    gasGaugeServo.setPeriodHertz(50);
    gasGaugeServo.attach(PIN_SERVO_GAS, 500, 2400);
    gasGaugeServo.write(SUDUT_ZONA_HIJAU);

    // Inisialisasi Layar TFT ILI9341
    tft.begin();
    tft.setRotation(1);
    perbaruiLayarTft();

    // Koneksi Wi-Fi Station
    WiFi.mode(WIFI_STA);
    Serial.print("[INFO] MAC Address ESP32 Indoor: ");
    Serial.println(WiFi.macAddress());

    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    int percobaan = 0;
    while (WiFi.status() != WL_CONNECTED && percobaan < 20) {
        delay(500);
        Serial.print(".");
        percobaan++;
    }
    Serial.println();

    // Inisialisasi Receiver ESP-NOW
    if (esp_now_init() == ESP_OK) {
        esp_now_register_recv_cb(OnDataRecv);
        Serial.println("[ESP-NOW] Receiver siap menangkap sinyal Bel dari Outdoor.");
    }
}

// ======================== 10. MAIN LOOP REAL-TIME INDOOR =============================
void loop() {
    unsigned long sekarang = millis();

    // A. TANGANI INTERUPSI BEL INSTAN DARI ESP-NOW OUTDOOR
    if (adaPanggilanBelBaru) {
        adaPanggilanBelBaru = false;
        perbaruiLayarTft();

        if (statusBelDariOutdoor == 1) {
            bunyikanBelTamu();
        } else if (statusBelDariOutdoor == 2) {
            bunyikanBelKurir();
        }

        // Langsung sinkronkan status terbaru ke server Laravel
        kirimTelemetriIndoorKeLaravel();
    }

    // Reset tampilan bel kembali ke normal setelah 8 detik
    if (statusBelDariOutdoor != 0 && (sekarang - waktuTerimaBel > 8000)) {
        statusBelDariOutdoor = 0;
        perbaruiLayarTft();
    }

    // B. BACA SENSOR MQ-2 & DHT22 SERTA GERAKKAN JARUM SERVO ANALOG (SETIAP 1 DETIK)
    if (sekarang - lastSensorRead >= SENSOR_INTERVAL) {
        lastSensorRead = sekarang;

        kadarGasAdc = analogRead(PIN_MQ2_GAS);
        float bacaSuhu = dht.readTemperature();
        float bacaLembap = dht.readHumidity();

        if (!isnan(bacaSuhu)) suhuRuangan = bacaSuhu;
        if (!isnan(bacaLembap)) kelembapanRuangan = bacaLembap;

        // Penentuan Zona Jarum Speedometer Servo Gas (Pin D13)
        if (kadarGasAdc >= GAS_BAHAYA_THRESHOLD) {
            sudutServoGas = SUDUT_ZONA_MERAH;  // 160 Derajat (Zona Merah Bahaya)
        } else if (kadarGasAdc >= GAS_WASPADA_THRESHOLD) {
            sudutServoGas = SUDUT_ZONA_KUNING; // 90 Derajat (Zona Kuning Waspada)
        } else {
            sudutServoGas = SUDUT_ZONA_HIJAU;  // 20 Derajat (Zona Hijau Aman)
        }

        gasGaugeServo.write(sudutServoGas);
        perbaruiLayarTft();
    }

    // C. AKTIFKAN SIRINE DARURAT KONTINYU JIKA KADAR GAS >= 3000
    if (kadarGasAdc >= GAS_BAHAYA_THRESHOLD) {
        bunyikanSirineGasBocor();
    }

    // D. SINKRONISASI BERKALA KE SERVER WEB LARAVEL SETIAP 5 DETIK
    if (sekarang - lastCloudSync >= CLOUD_INTERVAL) {
        lastCloudSync = sekarang;
        kirimTelemetriIndoorKeLaravel();
    }

    delay(20);
}

