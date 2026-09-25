#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <Adafruit_Fingerprint.h>
#include <freertos/FreeRTOS.h>
#include <freertos/queue.h>
#include <time.h>
#include "device_config.h"

#define RFID_SS_PIN 5
#define RFID_RST_PIN 4
#define BUZZER_PIN 26
#define GREEN_LED_PIN 25
#define RED_LED_PIN 27
#define YELLOW_LED_PIN 32
#define FINGER_RX_PIN 13
#define FINGER_TX_PIN 14

const char* API_BASE_URL = "https://payroll-system.up.railway.app/api/hardware";

MFRC522 rfid(RFID_SS_PIN, RFID_RST_PIN);
LiquidCrystal_I2C lcd(0x27, 16, 2);
HardwareSerial fingerSerial(2);
Adafruit_Fingerprint finger(&fingerSerial);
WiFiClientSecure secureClient;

bool fingerprintAvailable = false;
bool waitForFingerRelease = false;
unsigned long lastClockUpdate = 0;
unsigned long lastFingerprintScan = 0;
unsigned long lastWiFiAttempt = 0;
unsigned long fingerReleaseStarted = 0;
unsigned long lastFingerprintReconnect = 0;

const unsigned long WIFI_RETRY_MS = 10000;
const unsigned long ENROLLMENT_POLL_MS = 2000;
const unsigned long FINGER_RELEASE_TIMEOUT_MS = 5000;

// Keep enrollment connected so requests from the faculty form reach the device.
const bool ENABLE_REMOTE_ENROLLMENT = true;

struct EnrollmentRequest {
  char id[24];
  char method[16];
  char currentIdentifier[101];
};

QueueHandle_t enrollmentQueue = nullptr;
volatile bool enrollmentBusy = false;
volatile bool attendanceBusy = false;

void printLine(int row, String text) {
  text = text.substring(0, 16);
  while (text.length() < 16) text += " ";
  lcd.setCursor(0, row);
  lcd.print(text);
}

void showMessage(String first, String second) {
  printLine(0, first);
  printLine(1, second);
}

void successSignal() {
  digitalWrite(RED_LED_PIN, LOW);
  digitalWrite(YELLOW_LED_PIN, LOW);
  digitalWrite(GREEN_LED_PIN, HIGH);
  tone(BUZZER_PIN, 1500, 100);
  delay(150);
  tone(BUZZER_PIN, 1900, 150);
  delay(200);
  noTone(BUZZER_PIN);
  digitalWrite(GREEN_LED_PIN, LOW);
}

void doneSignal() {
  digitalWrite(RED_LED_PIN, LOW);
  digitalWrite(YELLOW_LED_PIN, LOW);
  digitalWrite(GREEN_LED_PIN, HIGH);
  tone(BUZZER_PIN, 1100, 180);
  delay(240);
  tone(BUZZER_PIN, 700, 220);
  delay(280);
  noTone(BUZZER_PIN);
  digitalWrite(GREEN_LED_PIN, LOW);
}

void errorSignal() {
  digitalWrite(GREEN_LED_PIN, LOW);
  digitalWrite(YELLOW_LED_PIN, LOW);
  digitalWrite(RED_LED_PIN, HIGH);
  tone(BUZZER_PIN, 350, 450);
  delay(500);
  noTone(BUZZER_PIN);
  digitalWrite(RED_LED_PIN, LOW);
}

String jsonValue(String json, String key) {
  String marker = String("\"") + key + "\":\"";
  int start = json.indexOf(marker);
  if (start < 0) return "";
  start += marker.length();
  int finish = json.indexOf("\"", start);
  return finish < 0 ? "" : json.substring(start, finish);
}

String rfidUid() {
  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(rfid.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  return uid;
}

void diagnoseConfiguredWiFi() {
  Serial.println("Scanning for the configured 2.4 GHz WiFi...");
  int networkCount = WiFi.scanNetworks(false, true);
  bool found = false;

  for (int index = 0; index < networkCount; index++) {
    if (WiFi.SSID(index) != WIFI_NAME) continue;

    found = true;
    Serial.print("Configured WiFi found. RSSI: ");
    Serial.print(WiFi.RSSI(index));
    Serial.print(" dBm, channel: ");
    Serial.print(WiFi.channel(index));
    Serial.print(", security code: ");
    Serial.println(static_cast<int>(WiFi.encryptionType(index)));
  }

  if (!found) {
    Serial.println("Configured WiFi was not found. Check the exact SSID and make sure 2.4 GHz is enabled.");
  }
  WiFi.scanDelete();
}

void connectWiFi() {
  lastWiFiAttempt = millis();
  digitalWrite(YELLOW_LED_PIN, HIGH);
  showMessage("Connecting WiFi", "Please wait");
  WiFi.disconnect();
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_NAME, WIFI_PASSWORD);
  for (int attempt = 0; WiFi.status() != WL_CONNECTED && attempt < 20; attempt++) delay(500);
  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("WiFi connected. IP: ");
    Serial.println(WiFi.localIP());
    successSignal();
    configTime(8 * 3600, 0, "pool.ntp.org", "time.nist.gov");
  } else {
    Serial.print("WiFi connection failed. Status: ");
    Serial.println(WiFi.status());
    diagnoseConfiguredWiFi();
    errorSignal();
    showMessage("WiFi Failed", "Check settings");
  }
  digitalWrite(YELLOW_LED_PIN, LOW);
}

void showClock() {
  time_t currentTime = time(nullptr);
  if (currentTime < 100000) {
    showMessage("Payroll System", "Time syncing...");
    return;
  }

  struct tm info;
  localtime_r(&currentTime, &info);
  char dateText[17];
  char timeText[17];
  strftime(dateText, sizeof(dateText), "%m/%d/%Y", &info);
  strftime(timeText, sizeof(timeText), "%I:%M:%S %p", &info);
  printLine(0, dateText);
  printLine(1, timeText);
}

void setupFingerprint() {
  fingerSerial.begin(57600, SERIAL_8N1, FINGER_RX_PIN, FINGER_TX_PIN);
  finger.begin(57600);
  fingerprintAvailable = false;
  for (int attempt = 1; attempt <= 5 && !fingerprintAvailable; attempt++) {
    fingerprintAvailable = finger.verifyPassword();
    if (!fingerprintAvailable) {
      Serial.println("AS608 detection attempt " + String(attempt) + " of 5 failed.");
      delay(400);
    }
  }
  if (fingerprintAvailable) {
    finger.getParameters();
    finger.getTemplateCount();
    Serial.println("AS608 fingerprint sensor detected.");
    Serial.println("Stored fingerprints: " + String(finger.templateCount));
  } else {
    Serial.println("AS608 fingerprint sensor NOT detected. Check 5V, GND, TX->D13 and RX->D14.");
  }
}

bool reconnectFingerprint() {
  if (fingerprintAvailable && finger.verifyPassword()) return true;

  showMessage("AS608 reconnect", "Please wait...");
  fingerprintAvailable = false;
  for (int attempt = 1; attempt <= 5 && !fingerprintAvailable; attempt++) {
    Serial.println("Reconnecting AS608, attempt " + String(attempt) + " of 5...");
    fingerprintAvailable = finger.verifyPassword();
    if (!fingerprintAvailable) delay(400);
  }

  if (fingerprintAvailable) {
    finger.getParameters();
    finger.getTemplateCount();
    Serial.println("AS608 reconnected. Stored fingerprints: " + String(finger.templateCount));
  }

  return fingerprintAvailable;
}

void addHardwareHeaders(HTTPClient& http) {
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Hardware-Key", HARDWARE_API_KEY);
}

bool sendEnrollmentResult(String id, String status, String identifier, String message) {
  HTTPClient http;
  String url = String(API_BASE_URL) + "/enrollments/" + id + "/result";
  if (!http.begin(secureClient, url)) return false;
  http.setTimeout(10000);
  addHardwareHeaders(http);
  String payload = "{\"status\":\"" + status + "\",\"identifier\":\"" + identifier + "\",\"message\":\"" + message + "\"}";
  int code = http.POST(payload);
  Serial.println("Enrollment result HTTP " + String(code) + ": " + http.getString());
  http.end();
  return code >= 200 && code < 300;
}

int fingerprintSlot(String code) {
  if (!code.startsWith("FP-")) return 0;
  return code.substring(3).toInt();
}

int findFreeFingerprintSlot() {
  if (finger.getParameters() != FINGERPRINT_OK ||
      finger.getTemplateCount() != FINGERPRINT_OK) {
    Serial.println("Could not read AS608 storage information.");
    return -1;
  }

  Serial.println("AS608 capacity: " + String(finger.capacity) +
                 ", stored: " + String(finger.templateCount));
  int maximum = finger.capacity > 0 ? finger.capacity : 127;
  for (int slot = 1; slot <= maximum; slot++) {
    uint8_t result = finger.loadModel(slot);
    if (result == FINGERPRINT_DBRANGEFAIL || result == FINGERPRINT_NOTFOUND) {
      Serial.println("Using free fingerprint slot " + String(slot) + ".");
      return slot;
    }
    if (result != FINGERPRINT_OK) {
      Serial.println("AS608 slot " + String(slot) + " read error: 0x" + String(result, HEX));
      return -1;
    }
  }
  return 0;
}

bool waitForFingerprintImage(unsigned long timeoutMs) {
  unsigned long started = millis();
  while (millis() - started < timeoutMs) {
    uint8_t result = finger.getImage();
    if (result == FINGERPRINT_OK) return true;
    if (result != FINGERPRINT_NOFINGER) return false;
    delay(80);
  }
  return false;
}

void waitForFingerRemoval() {
  unsigned long started = millis();
  while (millis() - started < 15000 && finger.getImage() != FINGERPRINT_NOFINGER) delay(80);
}

bool captureFingerprint(int slot) {
  showMessage("Place finger", "First scan");
  if (!waitForFingerprintImage(30000) || finger.image2Tz(1) != FINGERPRINT_OK) return false;
  showMessage("Remove finger", "");
  waitForFingerRemoval();
  delay(500);
  showMessage("Place same", "finger again");
  if (!waitForFingerprintImage(30000) || finger.image2Tz(2) != FINGERPRINT_OK) return false;
  if (finger.createModel() != FINGERPRINT_OK) return false;
  return finger.storeModel(slot) == FINGERPRINT_OK;
}

void enrollFingerprint(String id, String currentIdentifier) {
  if (!reconnectFingerprint()) {
    sendEnrollmentResult(id, "failed", "", "AS608 is not available.");
    errorSignal();
    showMessage("AS608 Error", "Check wiring");
    delay(2500);
    return;
  }

  int newSlot = findFreeFingerprintSlot();
  if (newSlot < 0) {
    sendEnrollmentResult(id, "failed", "", "Could not read AS608 fingerprint storage. Check sensor wiring and power.");
    errorSignal();
    showMessage("AS608 read error", "Check wiring");
    delay(2500);
    return;
  }
  if (newSlot == 0) {
    sendEnrollmentResult(id, "failed", "", "No free fingerprint slot.");
    errorSignal();
    showMessage("Finger memory", "is full");
    delay(2500);
    return;
  }

  if (!captureFingerprint(newSlot)) {
    sendEnrollmentResult(id, "failed", "", "Fingerprint capture failed.");
    errorSignal();
    showMessage("Enroll failed", "Try again");
    delay(2500);
    return;
  }

  String code = "FP-" + String(newSlot);
  if (sendEnrollmentResult(id, "completed", code, "")) {
    int oldSlot = fingerprintSlot(currentIdentifier);
    if (oldSlot > 0 && oldSlot != newSlot) finger.deleteModel(oldSlot);
    successSignal();
    showMessage("Fingerprint", "Registered");
  } else {
    finger.deleteModel(newSlot);
    errorSignal();
    showMessage("Server rejected", "fingerprint");
  }
  delay(2500);
}

void enrollRfid(String id) {
  showMessage("Tap RFID card", "Waiting...");
  unsigned long started = millis();
  while (millis() - started < 60000) {
    if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
      String uid = rfidUid();
      rfid.PICC_HaltA();
      rfid.PCD_StopCrypto1();
      if (sendEnrollmentResult(id, "completed", uid, "")) {
        successSignal();
        showMessage("RFID card", "Registered");
      } else {
        errorSignal();
        showMessage("Server rejected", "RFID card");
      }
      delay(2500);
      return;
    }
    delay(60);
  }
  sendEnrollmentResult(id, "failed", "", "RFID scan timed out.");
  errorSignal();
  showMessage("RFID timeout", "Try again");
  delay(2500);
}

void enrollmentPollTask(void* parameter) {
  WiFiClientSecure enrollmentClient;
  enrollmentClient.setInsecure();

  while (true) {
    bool queueIsEmpty = enrollmentQueue && uxQueueMessagesWaiting(enrollmentQueue) == 0;
    if (ENABLE_REMOTE_ENROLLMENT &&
        !enrollmentBusy &&
        !attendanceBusy &&
        queueIsEmpty &&
        WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      String url = String(API_BASE_URL) + "/enrollment";
      if (http.begin(enrollmentClient, url)) {
        http.setConnectTimeout(800);
        http.setTimeout(1500);
        addHardwareHeaders(http);
        int code = http.GET();
        String response = http.getString();
        http.end();

        if (code != 204 && code != 200) {
          Serial.println("Enrollment poll HTTP " + String(code) + ": " + response);
        }

        if (code == 200) {
          String id = jsonValue(response, "id");
          String method = jsonValue(response, "method");
          String currentIdentifier = jsonValue(response, "current_identifier");
          if (id != "") {
            EnrollmentRequest request = {};
            id.toCharArray(request.id, sizeof(request.id));
            method.toCharArray(request.method, sizeof(request.method));
            currentIdentifier.toCharArray(request.currentIdentifier, sizeof(request.currentIdentifier));
            xQueueSend(enrollmentQueue, &request, 0);
          }
        }
      }
    }

    vTaskDelay(pdMS_TO_TICKS(ENROLLMENT_POLL_MS));
  }
}

void processEnrollmentRequest() {
  if (!enrollmentQueue || enrollmentBusy) return;

  EnrollmentRequest request;
  if (xQueueReceive(enrollmentQueue, &request, 0) != pdTRUE) return;

  enrollmentBusy = true;
  digitalWrite(YELLOW_LED_PIN, HIGH);
  String method = String(request.method);
  if (method == "rfid") enrollRfid(String(request.id));
  else if (method == "fingerprint") enrollFingerprint(String(request.id), String(request.currentIdentifier));
  digitalWrite(YELLOW_LED_PIN, LOW);
  enrollmentBusy = false;
}

void sendAttendance(String identifier, String method) {
  attendanceBusy = true;
  unsigned long requestStarted = millis();
  digitalWrite(YELLOW_LED_PIN, HIGH);
  showMessage("Processing...", "Please wait");

  HTTPClient http;
  String url = String(API_BASE_URL) + "/tap";
  if (!http.begin(secureClient, url)) {
    attendanceBusy = false;
    digitalWrite(YELLOW_LED_PIN, LOW);
    errorSignal();
    showMessage("Connection", "failed");
    delay(2500);
    return;
  }
  http.setReuse(true);
  http.setConnectTimeout(1500);
  http.setTimeout(10000);
  addHardwareHeaders(http);
  String payload = "{\"method\":\"" + method + "\",\"identifier\":\"" + identifier + "\"}";
  int code = http.POST(payload);
  String response = http.getString();
  http.end();
  attendanceBusy = false;
  Serial.println("Attendance HTTP " + String(code) + ": " + response);
  Serial.println("Attendance response time: " + String(millis() - requestStarted) + " ms");
  digitalWrite(YELLOW_LED_PIN, LOW);
  if (code == 200) {
    String action = jsonValue(response, "action");
    String displayTime = jsonValue(response, "display_time");
    showMessage(jsonValue(response, "display_name"), attendanceDisplayLine(action, displayTime));
    if (action == "ALREADY OUT") doneSignal();
    else successSignal();
  } else if (code == 409) {
    errorSignal();
    showMessage(jsonValue(response, "display_name"), "NO SCHEDULE");
  } else {
    errorSignal();
    showMessage(code == 404 ? "Not registered" : "Server error", "Code " + String(code));
  }
  delay(2500);
}

void processRfidAttendance() {
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) return;
  String uid = rfidUid();
  Serial.println("RFID UID: " + uid);
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
  sendAttendance(uid, "rfid");
}

void processFingerprintAttendance() {
  if (!fingerprintAvailable || waitForFingerRelease || millis() - lastFingerprintScan < 500) return;
  uint8_t result = finger.getImage();
  if (result == FINGERPRINT_NOFINGER) return;
  lastFingerprintScan = millis();

  if (result != FINGERPRINT_OK) {
    Serial.println("Fingerprint getImage error: 0x" + String(result, HEX));
    return;
  }

  waitForFingerRelease = true;
  fingerReleaseStarted = millis();
  if (finger.image2Tz() == FINGERPRINT_OK && finger.fingerSearch() == FINGERPRINT_OK) {
    Serial.println("Fingerprint matched: FP-" + String(finger.fingerID));
    sendAttendance("FP-" + String(finger.fingerID), "fingerprint");
  } else {
    Serial.println("Fingerprint not registered or could not be converted.");
    errorSignal();
    showMessage("Fingerprint", "Not registered");
    delay(2000);
  }
}

String attendanceDisplayLine(String action, String displayTime) {
  if (action == "ALREADY OUT") return "DONE " + displayTime;
  return action + " " + displayTime;
}

void setup() {
  Serial.begin(115200);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(GREEN_LED_PIN, OUTPUT);
  pinMode(RED_LED_PIN, OUTPUT);
  pinMode(YELLOW_LED_PIN, OUTPUT);
  digitalWrite(GREEN_LED_PIN, LOW);
  digitalWrite(RED_LED_PIN, LOW);
  digitalWrite(YELLOW_LED_PIN, HIGH);
  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  SPI.begin(18, 19, 23, RFID_SS_PIN);
  rfid.PCD_Init();
  rfid.PCD_SetAntennaGain(MFRC522::RxGain_max);
  byte rfidVersion = rfid.PCD_ReadRegister(rfid.VersionReg);
  Serial.print("RC522 version: 0x");
  Serial.println(rfidVersion, HEX);
  if (rfidVersion == 0x00 || rfidVersion == 0xFF) {
    Serial.println("RC522 NOT detected. Check 3.3V and SPI wiring.");
  }
  setupFingerprint();
  secureClient.setInsecure();
  WiFi.setTxPower(WIFI_POWER_15dBm);
  connectWiFi();
  enrollmentQueue = xQueueCreate(1, sizeof(EnrollmentRequest));
  if (enrollmentQueue) {
    xTaskCreatePinnedToCore(enrollmentPollTask, "enrollment-poll", 12288, nullptr, 1, nullptr, 0);
    Serial.println("Background enrollment polling started.");
  } else {
    Serial.println("Could not create the enrollment request queue.");
  }
  showMessage("Payroll System", "Ready to tap");
  digitalWrite(YELLOW_LED_PIN, LOW);
  delay(1000);
}

void loop() {
  if (WiFi.status() != WL_CONNECTED && millis() - lastWiFiAttempt >= WIFI_RETRY_MS) {
    connectWiFi();
  }

  if (millis() - lastClockUpdate >= 1000) {
    showClock();
    lastClockUpdate = millis();
  }

  if (!fingerprintAvailable && millis() - lastFingerprintReconnect >= 10000) {
    lastFingerprintReconnect = millis();
    fingerprintAvailable = finger.verifyPassword();
    if (fingerprintAvailable) Serial.println("AS608 reconnected for attendance.");
  }

  if (waitForFingerRelease) {
    uint8_t releaseResult = finger.getImage();
    if (releaseResult == FINGERPRINT_NOFINGER ||
        releaseResult == FINGERPRINT_PACKETRECIEVEERR ||
        millis() - fingerReleaseStarted >= FINGER_RELEASE_TIMEOUT_MS) {
      waitForFingerRelease = false;
    }
  } else {
    processFingerprintAttendance();
  }

  processRfidAttendance();
  processEnrollmentRequest();
}
