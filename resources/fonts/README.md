# ฟอนต์สำหรับประทับลายเซ็นในเอกสาร PDF

ระบบลงนาม 4 ลำดับ (งานวิจัยและพัฒนานวัตกรรม) เขียนชื่อ-สกุล ตำแหน่ง วันเวลา และเลขที่เอกสารลงใน PDF
ด้วยฟอนต์ TrueType (`.ttf`) ที่รองรับภาษาไทย โดยเลือกฟอนต์ตามลำดับนี้

1. ค่า `research.signing_font` ใน `config/local.php` (path เต็มของไฟล์ .ttf)
2. ไฟล์ `.ttf` ไฟล์แรกในโฟลเดอร์นี้ (`resources/fonts/`)
3. ฟอนต์ไทยของระบบปฏิบัติการ เช่น Tahoma / Leelawadee (Windows), Loma / Garuda / Noto Sans Thai (Linux)

บนเซิร์ฟเวอร์ Linux ที่ไม่มีฟอนต์ไทย ให้วางไฟล์ฟอนต์ เช่น `THSarabunNew.ttf` หรือ `Sarabun-Regular.ttf`
ไว้ในโฟลเดอร์นี้ (ไฟล์ .ttf ไม่ถูกเก็บใน git) หรือติดตั้งแพ็กเกจ `fonts-tlwg-loma` / `fonts-noto-core`
