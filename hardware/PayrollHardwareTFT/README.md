# Payroll Hardware - 3.5-inch ILI9488 TFT

This is the TFT variant of the payroll attendance firmware. The original
16x2 I2C version remains in `hardware/PayrollHardware`.

## Arduino libraries

Install these through **Tools > Manage Libraries**:

- LovyanGFX by lovyan03
- MFRC522 by GithubCommunity
- Adafruit Fingerprint Sensor Library by Adafruit

The ESP32 board package supplies WiFi, HTTPClient, LittleFS, Preferences,
FreeRTOS, SPI, and time support.

## Power distribution

Use the regulated 5V 3A supply. Do not connect its 5V output to the ESP32
`3V3` pin.

| Power connection | Destination |
| --- | --- |
| Supply `+5V` | ESP32 `VIN/5V`, TFT `VCC`, AS608 `VCC` |
| Supply `GND` | ESP32 `GND` and the GND of every module |
| ESP32 `3V3` | RC522 `3.3V`, TFT `LED` |

Every module must use the same common ground. Disconnect the external 5V
supply while uploading through USB to avoid powering the ESP32 from two
sources at the same time.

## TFT wiring (ILI9488 SPI)

| TFT pin | ESP32 pin |
| --- | --- |
| VCC | 5V (this MSP3520-style module accepts 3.3V to 5V) |
| GND | GND |
| SCK / CLK | GPIO 16 |
| SDO / MISO | Not connected |
| SDI / MOSI | GPIO 17 |
| CS | GPIO 21 |
| DC / RS | GPIO 22 |
| RESET / RST | GPIO 33 |
| LED / BL | ESP32 3V3 (backlight always on) |

## RC522 RFID wiring

| RC522 pin | ESP32 pin |
| --- | --- |
| `3.3V` | `3V3` |
| `GND` | `GND` |
| `SDA / SS` | GPIO 5 |
| `SCK` | GPIO 18 |
| `MOSI` | GPIO 23 |
| `MISO` | GPIO 19 |
| `RST` | GPIO 4 |
| `IRQ` | Not connected |

Never connect the RC522 to 5V. The RC522 and TFT now use separate SPI buses,
so none of their SPI signal wires need to be joined together.

## AS608 fingerprint wiring

| AS608 wire/pin | ESP32 connection |
| --- | --- |
| `VCC` | External regulated 5V |
| `GND` | Common GND |
| Sensor `TX` | GPIO 13 (ESP32 RX) |
| Sensor `RX` | GPIO 14 (ESP32 TX) |

TX and RX are crossed: sensor TX goes to the ESP32 receive pin.

## Indicators and buzzer

Use a 220-ohm to 330-ohm series resistor for every bare LED.

| Component | ESP32 connection |
| --- | --- |
| Green LED anode | GPIO 25 through resistor |
| Red LED anode | GPIO 27 through resistor |
| Yellow LED anode | GPIO 32 through resistor |
| All LED cathodes | Common GND |
| Passive buzzer positive / signal | GPIO 26 |
| Passive buzzer negative | Common GND |

For a three-pin buzzer module, connect `S` to GPIO 26, `-` to GND, and `+`
to the voltage printed on that module.

## Unused TFT connections

Touch is intentionally disabled in this first version. Leave `T_CS` and
`T_IRQ` disconnected. Also leave `T_CLK`, `T_DIN`, and `T_DO` disconnected.
The MicroSD pins `SD_SCK`, `SD_MISO`, `SD_MOSI`, and `SD_CS` are also unused.

Do not connect the `LED` pin directly to 5V. The module documentation calls
for a 3.3V high level on this backlight-control pin. ESP32 control signals are
also 3.3V logic even though the module's `VCC` is connected to 5V.

If the expansion board VCC selector is set to 5V, use that VCC row only for
the TFT `VCC` and AS608 `VCC`. Power the RC522 from the dedicated ESP32 `3V3`
pin, and connect the TFT `LED` to that same dedicated `3V3` supply.

Before uploading, confirm that the board is the 3.5-inch SPI `ILI9488`
variant. Do not use this pinout for a parallel 8-bit display.

## White-screen test

If the backlight is on but the screen stays white, upload
`hardware/TFTTest/TFTTest.ino` first. Disconnect the RC522, AS608, LEDs, and
buzzer during this test. The test uses the seller's ILI9488 18-bit
initialization sequence and software SPI. A correct connection repeatedly
shows red, green, blue, black, and yellow. If it remains white, recheck `CS`,
`RESET`, `DC/RS`, `SDI/MOSI`, `SCK`, and the J1 state; the problem is before
the payroll firmware starts.

The ESP32 uses 3.3V GPIO logic, so leave the display's `J1` solder jumper in
its factory-default open state. Do not bridge J1. The seller guide instructs
bridging J1 only for 5V-logic boards such as the Arduino Uno or Mega.

## Complete GPIO map

| ESP32 GPIO | Assigned device |
| --- | --- |
| GPIO 4 | RC522 RST |
| GPIO 5 | RC522 SS/SDA |
| GPIO 13 | AS608 TX to ESP32 RX |
| GPIO 14 | ESP32 TX to AS608 RX |
| GPIO 16 | TFT SCK |
| GPIO 17 | TFT MOSI |
| GPIO 18 | RC522 SCK |
| GPIO 19 | RC522 MISO |
| GPIO 21 | TFT CS |
| GPIO 22 | TFT DC/RS |
| GPIO 23 | RC522 MOSI |
| GPIO 25 | Green LED |
| GPIO 26 | Buzzer |
| GPIO 27 | Red LED |
| GPIO 32 | Yellow LED |
| GPIO 33 | TFT RESET |
