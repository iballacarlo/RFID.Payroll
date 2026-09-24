#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <Adafruit_Fingerprint.h>
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
unsigned long lastEnrollmentPoll = 0;

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

void connectWiFi() {
  showMessage("Connecting WiFi", "Please wait");
  WiFi.begin(WIFI_NAME, WIFI_PASSWORD);
  for (int attempt = 0; WiFi.status() != WL_CONNECTED && attempt < 30; attempt++) delay(500);
  if (WiFi.status() == WL_CONNECTED) {
    successSignal();
    configTime(8 * 3600, 0, "pool.ntp.org", "time.nist.gov");
  } else {
    errorSignal();
    showMessage("WiFi Failed", "Check settings");
    delay(2000);
  }
}

void showClock() {
  struct tm info;
  if (!getLocalTime(&info)) {
    showMessage("Payroll System", "Time syncing");
    return;
  }
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
  fingerprintAvailable = finger.verifyPassword();
  if (fingerprintAvailable) finger.getParameters();
}

void addHardwareHeaders(HTTPClient& http) {
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Hardware-Key", API_KEY);
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
  int maximum = finger.capacity > 0 ? finger.capacity : 127;
  for (int slot = 1; slot <= maximum; slot++) {
    if (finger.loadModel(slot) == FINGERPRINT_NOTFOUND) return slot;
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
  if (!fingerprintAvailable) {
    sendEnrollmentResult(id, "failed", "", "AS608 is not available.");
    errorSignal();
    showMessage("AS608 Error", "Check wiring");
    delay(2500);
    return;
  }

  int newSlot = findFreeFingerprintSlot();
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

void checkEnrollment() {
  if (WiFi.status() != WL_CONNECTED || millis() - lastEnrollmentPoll < 2000) return;
  lastEnrollmentPoll = millis();
  HTTPClient http;
  String url = String(API_BASE_URL) + "/enrollment";
  if (!http.begin(secureClient, url)) return;
  http.setTimeout(7000);
  addHardwareHeaders(http);
  int code = http.GET();
  String response = http.getString();
  http.end();
  if (code != 200) return;

  String id = jsonValue(response, "id");
  String method = jsonValue(response, "method");
  String currentIdentifier = jsonValue(response, "current_identifier");
  if (id == "") return;
  digitalWrite(YELLOW_LED_PIN, HIGH);
  if (method == "rfid") enrollRfid(id);
  else if (method == "fingerprint") enrollFingerprint(id, currentIdentifier);
  digitalWrite(YELLOW_LED_PIN, LOW);
}

void sendAttendance(String identifier, String method) {
  HTTPClient http;
  String url = String(API_BASE_URL) + "/tap";
  if (!http.begin(secureClient, url)) return;
  http.setTimeout(10000);
  addHardwareHeaders(http);
  String payload = "{\"method\":\"" + method + "\",\"identifier\":\"" + identifier + "\"}";
  int code = http.POST(payload);
  String response = http.getString();
  http.end();
  if (code == 200) {
    successSignal();
    showMessage(jsonValue(response, "display_name"), jsonValue(response, "action") + " " + jsonValue(response, "display_time"));
  } else {
    errorSignal();
    showMessage(code == 404 ? "Not registered" : "Server error", "Code " + String(code));
  }
  delay(2500);
}

void processRfidAttendance() {
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) return;
  String uid = rfidUid();
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
  sendAttendance(uid, "rfid");
}

void processFingerprintAttendance() {
  if (!fingerprintAvailable || waitForFingerRelease || millis() - lastFingerprintScan < 500) return;
  uint8_t result = finger.getImage();
  if (result == FINGERPRINT_NOFINGER) return;
  lastFingerprintScan = millis();
  waitForFingerRelease = true;
  if (result == FINGERPRINT_OK && finger.image2Tz() == FINGERPRINT_OK && finger.fingerSearch() == FINGERPRINT_OK) {
    sendAttendance("FP-" + String(finger.fingerID), "fingerprint");
  } else {
    errorSignal();
    showMessage("Fingerprint", "Not registered");
    delay(2000);
  }
}

void setup() {
  Serial.begin(115200);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(GREEN_LED_PIN, OUTPUT);
  pinMode(RED_LED_PIN, OUTPUT);
  pinMode(YELLOW_LED_PIN, OUTPUT);
  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  SPI.begin(18, 19, 23, RFID_SS_PIN);
  rfid.PCD_Init();
  setupFingerprint();
  secureClient.setInsecure();
  connectWiFi();
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) connectWiFi();
  checkEnrollment();
  if (waitForFingerRelease) {
    if (finger.getImage() == FINGERPRINT_NOFINGER) waitForFingerRelease = false;
    return;
  }
  processRfidAttendance();
  processFingerprintAttendance();
  if (millis() - lastClockUpdate >= 1000) {
    showClock();
    lastClockUpdate = millis();
  }
}
