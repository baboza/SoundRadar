# SoundRadar — Khaen Community Web Application
แคนอีสาน: แพลตฟอร์มพิกัดช่างแคน หมอแคน และตลาดเครื่องดนตรีอีสาน

## สถาปัตยกรรมและโครงสร้างโปรเจกต์ (Project Structure)
```
c:\xampp\htdocs\SoundRadar\
├── schema.sql           # โครงสร้างฐานข้อมูล MySQL และข้อมูลจำลอง (Mock Data)
├── db.php               # ไฟล์เชื่อมต่อฐานข้อมูลด้วย PHP PDO (UTF-8, Prepared Statements)
├── auth_helper.php      # ระบบจำลอง Session Authentication (Mock Admin / User / Google SSO Hook)
├── get_locations.php    # REST API ส่งออกข้อมูล JSON พิกัดหมอแคน/ช่างแคน/ร้านค้า
├── update_location.php  # REST API สำหรับให้ผู้ใช้อัปเดตพิกัด GPS ของตนเอง
├── index.php            # หน้าหลัก (Leaflet.js Map + หมอแคนใกล้ฉัน + Marketplace Grid)
└── README.md            # คู่มือการติดตั้งและใช้งาน
```

## ขั้นตอนการติดตั้งและเริ่มต้นใช้งาน (Quick Setup)

1. **เปิดบริการใน XAMPP:**
   - เปิดโปรแกรม **XAMPP Control Panel** แล้วกด **Start** ที่โมดูล **Apache** และ **MySQL**

2. **นำเข้าฐานข้อมูล MySQL:**
   - เปิดเบราว์เซอร์ไปที่ `http://localhost/phpmyadmin/`
   - ไปที่แท็บ **Import** หรือ **SQL**
   - รันคำสั่งจากไฟล์ [schema.sql](file:///c:/xampp/htdocs/SoundRadar/schema.sql) เพื่อสร้างฐานข้อมูล `khaen_community` พร้อม Mock Data ทันที

3. **เข้าใช้งานแอปพลิเคชัน:**
   - เข้าผ่านเว็บเบราว์เซอร์ที่: `http://localhost/SoundRadar/`

## ฟีเจอร์หลักในระบบ
- **แผนที่และพิกัด Leaflet.js:**
  - รองรับการกรอง 3 หมวดหมู่ (ช่างแคน, หมอแคน, ร้านขายแคน)
  - ฟังก์ชัน **"หมอแคนใกล้ฉัน (Near Me)"** ค้นหาพิกัดผู้ใช้ผ่าน HTML5 Geolocation API
  - Marker ออกแบบในธีม Modern Isan (สีเนื้อไม้ไผ่, สีครามหม้อฮ่อม, สีอิฐมอญ)
- **ระบบสมาชิกและสิทธิ์ (Roles):**
  - มีปุ่มสลับบทบาท (Admin / User / Guest) จำลองที่มุมบนขวา
  - ผู้ใช้งานสามารถคลิก "ปักหมุดพิกัดของฉัน" เพื่ออัปเดตตำแหน่งลงฐานข้อมูลได้ทันที
- **ตลาดซื้อขายแคน (Marketplace):**
  - แสดงผลในรูปแบบ Responsive Grid พร้อมราคา, คีย์เสียง, และข้อมูลผู้ขาย
