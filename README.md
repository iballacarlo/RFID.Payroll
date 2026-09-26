# CvSU - Imus Payroll System

Payroll and attendance management system for Cavite State University - Imus Campus, Department of Computer Studies.

## Main features

- Faculty account onboarding and profile management
- RFID and fingerprint registration
- Schedule-based attendance and online DTR
- Payroll period generation and A4 payslips
- Email verification and password recovery
- Administrative backup and restore
- Responsive light and dark modes

## Attendance hardware

The firmware in `hardware/PayrollHardware/PayrollHardware.ino` supports:

- ESP32 DevKit V1
- MFRC522 RFID reader
- AS608 fingerprint sensor
- 16x2 I2C LCD
- Buzzer and status LEDs

Before uploading the sketch, copy `device_config.example.h` as `device_config.h`, then configure `WIFI_NAME`, `WIFI_PASSWORD`, and `HARDWARE_API_KEY`. The firmware key must exactly match the Railway `HARDWARE_API_KEY` variable. The real configuration file is ignored by Git.

The configured API base is `https://payroll-system.up.railway.app/api/hardware`. Update it if the Railway public domain changes.

### Enrollment flow

1. Save the faculty profile.
2. Open **Faculty -> Profile & IDs**.
3. Select **Register** or **Re-register** for RFID or fingerprint.
4. Follow the prompts shown on the attendance device.
5. The ESP32 sends the captured RFID UID or fingerprint template ID to the system.

Enrollment requests expire after three minutes. The ESP32 checks for a pending registration request every two seconds.

### ESP32 pin map

| Device | ESP32 pin |
| --- | --- |
| RC522 SS/SDA | GPIO 5 |
| RC522 RST | GPIO 4 |
| RC522 SCK/MISO/MOSI | GPIO 18/19/23 |
| AS608 RX/TX | GPIO 13/14 |
| I2C SDA/SCL | GPIO 21/22 |
| Green/Red/Yellow LED | GPIO 25/27/32 |
| Buzzer | GPIO 26 |
