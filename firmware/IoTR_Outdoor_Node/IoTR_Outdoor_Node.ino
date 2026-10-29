/*
 * ======================================================================================
 * IoTR // NEXUS — FIRMWARE ESP32 UNIT OUTDOOR (SMART BELL KY-004 & AUTO SOLAR TRACKER)
 * ======================================================================================
 * Identitas Pabrikan : IOTR-OUTDOOR-01
 * Password Klaim     : OUTDOOR123
 * API Token Internal : IOTR_SECRET_TOKEN_OUTDOOR_ESP32_998877
 * Arsitektur         : Hybrid Dual-Protocol (ESP-NOW Instant Peer + Wi-Fi HTTP POST Cloud)
 * ======================================================================================
 */

#include <WiFi.h>
#include <esp_now.h>
#include <HTTPClient.h>
#include <ESP32Servo.h>

// ======================== 1. KONFIGURASI JARINGAN & CLOUD API ========================
const char* WIFI_SSID     = "NAMA_WIFI_ANDA";
const char* WIFI_PASSWORD = "PASSWORD_WIFI_ANDA";
const char* SERVER_URL    = "http://192.168.1.100:8000/api/telemetry"; // Ganti dengan IP/Domain Laravel Anda
const char* API_TOKEN     = "IOTR_SECRET_TOKEN_OUTDOOR_ESP32_998877";

// MAC Address ESP32 Unit Indoor (Ganti sesuai MAC Address ESP32 Indoor Anda, atau FF:FF:FF:FF:FF:FF untuk Broadcast)
uint8_t indoorMacAddress[] = {0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF};

// ======================== 2. PEMETAAN PIN HARDWARE OUTDOOR ===========================
#define PIN_BUTTON_BEL  27  // Modul Tombol Taktil KY-004 (Active LOW dengan INPUT_PULLUP)
#define PIN_LDR_TIMUR   34  // ADC1_CH6 Sensor Cahaya LDR Sisi Timur (0 - 4095)
#define PIN_LDR_BARAT   35  // ADC1_CH7 Sensor Cahaya LDR Sisi Barat (0 - 4095)
#define PIN_SERVO_SOLAR 14  // PWM Servo SG90 Penjejak Matahari (Solar Tracker)
#define PIN_LED_STATUS  2   // Indikator LED Onboard ESP32

// ======================== 3. STRUKTUR PAKET KOMUNIKASI ESP-NOW =======================
typedef struct struct_espnow_packet {
    int status_bel;      // 0 = Standby, 1 = Tamu (1x Klik), 2 = Kurir Paket (3x Klik)
    int sudut_solar;     // Derajat posisi Servo Solar Tracker (0 - 180)
    int cahaya_timur;    // Nilai ADC LDR Timur
    int cahaya_barat;    // Nilai ADC LDR Barat
} struct_espnow_packet;

struct_espnow_packet outgoingPacket;
esp_now_peer_info_t peerInfo;
Servo solarServo;

// ======================== 4. VARIABEL STATE & MULTI-CLICK DETECTOR ===================
int posisiServoSolar       = 90;   // Posisi awal Zenith (90 derajat)
const int DEADBAND_LDR     = 120;  // Ambang toleransi selisih cahaya agar servo stabil
const int STEP_SERVO       = 2;    // Pergeseran derajat per siklus koreksi

int statusBelAktif         = 0;
int jumlahKlik             = 0;
bool tombolSebelumnya      = HIGH;
unsigned long waktuKlikTerakhir  = 0;
unsigned long waktuResetBel      = 0;
const unsigned long JEDA_MULTIKLIK = 650;  // Jeda tunggu deteksi multi-klik (ms)
const unsigned long DURASI_AKTIF_BEL = 8000; // Status bel kembali ke 0 setelah 8 detik

unsigned long lastCloudSync      = 0;
const unsigned long CLOUD_INTERVAL = 5000; // Kirim HTTP POST ke Laravel setiap 5 detik

// ======================== 5. CALLBACK PENGIRIMAN ESP-NOW =============================
void OnDataSent(const uint8_t *mac_addr, esp_now_send_status_t status) {
    Serial.print("[ESP-NOW TX] Status Pengiriman ke Indoor: ");
    Serial.println(status == ESP_NOW_SEND_SUCCESS ? "SUKSES (INSTANT)" : "GAGAL");
}

// ======================== 6. FUNGSI KIRIM PAKET KE ESP32 INDOOR ======================
void kirimPaketEspNow(int kodeBel, int sudut, int timur, int barat) {
    outgoingPacket.status_bel   = kodeBel;
    outgoingPacket.sudut_solar  = sudut;
    outgoingPacket.cahaya_timur = timur;
    outgoingPacket.cahaya_barat = barat;

    esp_now_send(indoorMacAddress, (uint8_t *) &outgoingPacket, sizeof(outgoingPacket));
}

// ======================== 7. FUNGSI KIRIM HTTP POST KE LARAVEL CLOUD =================
void kirimTelemetriKeLaravel(int kodeBel, int sudut, int timur, int barat) {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("[WIFI] Terputus! Mencoba menyambung ulang...");
        WiFi.reconnect();
        return;
    }

    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Accept", "application/json");
    http.addHeader("Authorization", String("Bearer ") + API_TOKEN);

    String jsonPayload = "{";
    jsonPayload += "\"status_bel\":" + String(kodeBel) + ",";
    jsonPayload += "\"sudut_solar\":" + String(sudut) + ",";
    jsonPayload += "\"cahaya_timur\":" + String(timur) + ",";
    jsonPayload += "\"cahaya_barat\":" + String(barat);
    jsonPayload += "}";

    int httpResponseCode = http.POST(jsonPayload);

    if (httpResponseCode > 0) {
        Serial.printf("[CLOUD HTTP POST] Kode: %d | Payload: %s\n", httpResponseCode, jsonPayload.c_str());
    } else {
        Serial.printf("[CLOUD ERROR] Gagal mengirim HTTP POST: %s\n", http.errorToString(httpResponseCode).c_str());
    }

    http.end();
}

// ======================== 8. SETUP AWAL MIKROKONTROLER ===============================
void setup() {
    Serial.begin(115200);
    delay(500);
    Serial.println("\n=======================================================");
    Serial.println("IoTR // NEXUS — OUTDOOR NODE (IOTR-OUTDOOR-01) BOOTING");
    Serial.println("=======================================================");

    pinMode(PIN_BUTTON_BEL, INPUT_PULLUP);
    pinMode(PIN_LDR_TIMUR, INPUT);
    pinMode(PIN_LDR_BARAT, INPUT);
    pinMode(PIN_LED_STATUS, OUTPUT);

    // Inisialisasi Servo Solar Tracker SG90
    solarServo.setPeriodHertz(50);
    solarServo.attach(PIN_SERVO_SOLAR, 500, 2400);
    solarServo.write(posisiServoSolar);

    // Set mode Wi-Fi ke Station (Wajib untuk hybrid ESP-NOW + Wi-Fi HTTP Client)
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    Serial.print("[WIFI] Menghubungkan ke jaringan Wi-Fi");

    int percobaan = 0;
    while (WiFi.status() != WL_CONNECTED && percobaan < 20) {
        delay(500);
        Serial.print(".");
        percobaan++;
    }
    Serial.println();

    if (WiFi.status() == WL_CONNECTED) {
        Serial.print("[WIFI] Terhubung! IP Outdoor: ");
        Serial.println(WiFi.localIP());
    } else {
        Serial.println("[WIFI] Mode Offline: Tetap menjalankan ESP-NOW & Solar Tracker.");
    }

    // Inisialisasi Protokol ESP-NOW
    if (esp_now_init() != ESP_OK) {
        Serial.println("[ESP-NOW] Gagal menginisialisasi ESP-NOW!");
        return;
    }
    esp_now_register_send_cb(OnDataSent);

    memcpy(peerInfo.peer_addr, indoorMacAddress, 6);
    peerInfo.channel = 0;
    peerInfo.encrypt = false;

    if (esp_now_add_peer(&peerInfo) != ESP_OK) {
        Serial.println("[ESP-NOW] Gagal menambahkan Peer Unit Indoor!");
    } else {
        Serial.println("[ESP-NOW] Peer Unit Indoor siap menerima sinyal instan.");
    }
}

// ======================== 9. MAIN LOOP REAL-TIME =====================================
void loop() {
    unsigned long sekarang = millis();

    // A. BACA INTENSITAS CAHAYA LDR TIMUR & BARAT + KENDALI SERVO SOLAR TRACKER
    int nilaiTimur = analogRead(PIN_LDR_TIMUR);
    int nilaiBarat = analogRead(PIN_LDR_BARAT);
    int selisihCahaya = nilaiTimur - nilaiBarat;

    if (abs(selisihCahaya) > DEADBAND_LDR) {
        if (selisihCahaya > 0) {
            // Matahari lebih terang di Timur -> Condongkan panel ke Timur (Mendekati 20 derajat)
            posisiServoSolar = max(20, posisiServoSolar - STEP_SERVO);
        } else {
            // Matahari lebih terang di Barat -> Condongkan panel ke Barat (Mendekati 160 derajat)
            posisiServoSolar = min(160, posisiServoSolar + STEP_SERVO);
        }
        solarServo.write(posisiServoSolar);
    }

    // B. DETEKSI TOMBOL BEL KY-004 (1x KLIK = TAMU, >=3x KLIK = KURIR PAKET)
    bool kondisiTombol = digitalRead(PIN_BUTTON_BEL);

    if (kondisiTombol == LOW && tombolSebelumnya == HIGH) {
        delay(45); // Debounce mekanik saklar
        if (digitalRead(PIN_BUTTON_BEL) == LOW) {
            jumlahKlik++;
            waktuKlikTerakhir = sekarang;
            digitalWrite(PIN_LED_STATUS, HIGH);
            Serial.printf("[KY-004] Klik terdeteksi! Total klik sementara: %d\n", jumlahKlik);
        }
    }
    tombolSebelumnya = kondisiTombol;

    // Evaluasi jumlah klik setelah jeda multi-klik selesai
    if (jumlahKlik > 0 && (sekarang - waktuKlikTerakhir > JEDA_MULTIKLIK)) {
        if (jumlahKlik >= 3) {
            statusBelAktif = 2; // KURIR PAKET (3x Klik)
            Serial.println("[EVENT BEL] 3x KLIK -> KURIR PAKET DATANG!");
        } else {
            statusBelAktif = 1; // TAMU DATANG (1x Klik)
            Serial.println("[EVENT BEL] 1x KLIK -> TAMU DATANG!");
        }

        jumlahKlik = 0;
        waktuResetBel = sekarang;
        digitalWrite(PIN_LED_STATUS, LOW);

        // 1. Kirim sinyal instan ke ESP32 Indoor via ESP-NOW tanpa jeda!
        kirimPaketEspNow(statusBelAktif, posisiServoSolar, nilaiTimur, nilaiBarat);

        // 2. Langsung tembak HTTP POST ke Web Dashboard Laravel agar kartu UI merespons real-time!
        kirimTelemetriKeLaravel(statusBelAktif, posisiServoSolar, nilaiTimur, nilaiBarat);
        lastCloudSync = sekarang;
    }

    // Kembalikan status_bel ke 0 (Standby) setelah DURASI_AKTIF_BEL selesai
    if (statusBelAktif != 0 && (sekarang - waktuResetBel > DURASI_AKTIF_BEL)) {
        statusBelAktif = 0;
        Serial.println("[EVENT BEL] Kembali ke mode STANDBY (0).");
        kirimPaketEspNow(statusBelAktif, posisiServoSolar, nilaiTimur, nilaiBarat);
    }

    // C. SINKRONISASI BERKALA KE SERVER LARAVEL SETIAP 5 DETIK
    if (sekarang - lastCloudSync >= CLOUD_INTERVAL) {
        lastCloudSync = sekarang;
        kirimTelemetriKeLaravel(statusBelAktif, posisiServoSolar, nilaiTimur, nilaiBarat);
    }

    delay(20);
}

