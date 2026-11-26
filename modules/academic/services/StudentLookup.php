<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Modules\CoreAdmin\Services\ServiceRoomService;

/**
 * ค่าคงที่/ตัวเลือกของทะเบียนนักเรียน อ้างอิงแบบฟอร์มระบบ SET (สพฐ.)
 * และใบสมัครขอรับบริการช่วยเหลือระยะแรกเริ่มของศูนย์การศึกษาพิเศษ ประจำจังหวัดพะเยา
 */
final class StudentLookup
{
    public const STATUSES = [
        'applicant' => ['ผู้สมัคร (รออนุมัติ)', 'warning'],
        'active' => ['กำลังรับบริการ', 'success'],
        'suspended' => ['พักการเรียน', 'warning'],
        'graduated' => ['จบ/ส่งต่อ', 'info'],
        'transferred' => ['ย้ายสถานศึกษา', 'secondary'],
        'discharged' => ['จำหน่าย', 'dark'],
        'deceased' => ['เสียชีวิต', 'dark'],
    ];

    /** สถานะที่ต้องระบุวันที่ออกจากการรับบริการ */
    public const LEFT_STATUSES = ['graduated', 'transferred', 'discharged', 'deceased'];

    /** สถานะที่ยังอยู่ในความดูแลของศูนย์ (ใช้ตรวจความครบถ้วนและยกยอดปีการศึกษา) */
    public const OPEN_STATUSES = ['applicant', 'active', 'suspended'];

    /** สถานะแรกที่เลือกได้ตอนเพิ่มนักเรียน (สถานะอื่นต้องเปลี่ยนผ่านเมนูเปลี่ยนสถานะ) */
    public const INITIAL_STATUSES = ['applicant', 'active'];

    /**
     * การเปลี่ยนสถานะที่อนุญาต (แผนภาพสถานะในเอกสารวิเคราะห์ Phase 5)
     * สถานะสิ้นสุดกลับเข้ารับบริการใหม่ได้ (ยกเว้นเสียชีวิต) โดยต้องระบุเหตุผล
     */
    public const STATUS_TRANSITIONS = [
        'applicant' => ['active', 'discharged'],
        'active' => ['suspended', 'graduated', 'transferred', 'discharged', 'deceased'],
        'suspended' => ['active', 'transferred', 'discharged', 'deceased'],
        'graduated' => ['active'],
        'transferred' => ['active'],
        'discharged' => ['active'],
        'deceased' => [],
    ];

    /** ประเภทลูกเสือ (รหัสตามระบบ SET: Stu_Scout) */
    public const SCOUT_LEVELS = [
        '00' => 'อนุบาล/เตรียมความพร้อม',
        '01' => 'สำรอง (ป.1-ป.3)',
        '02' => 'สามัญ (ป.4-ป.6)',
        '03' => 'สามัญรุ่นใหญ่ (ม.1-ม.3)',
        '04' => 'วิสามัญ (ม.ปลาย)',
    ];

    /** เหล่าลูกเสือ (SET: Stu_Groupscout) */
    public const SCOUT_GROUPS = [
        '1' => 'ลูกเสือ/เนตรนารี',
        '2' => 'เหล่าอากาศ',
        '3' => 'เหล่าสมุทร',
        '4' => 'ไม่เป็นลูกเสือ/เนตรนารี',
        '9' => 'ยังไม่ได้จัดเก็บข้อมูล',
    ];

    /** ยุวกาชาด (SET: Stu_Redcrossyouth) */
    public const REDCROSS_STATUSES = [
        '1' => 'เป็นสมาชิกยุวกาชาด',
        '2' => 'ไม่เป็นสมาชิกยุวกาชาด',
        '9' => 'ยังไม่ได้จัดเก็บข้อมูล',
    ];

    /** ข้อมูลพี่น้อง => ชื่อ */
    public const SIBLING_FIELDS = [
        'older_brothers' => 'จำนวนพี่ชาย',
        'younger_brothers' => 'จำนวนน้องชาย',
        'older_sisters' => 'จำนวนพี่สาว',
        'younger_sisters' => 'จำนวนน้องสาว',
        'child_order' => 'เป็นบุตรคนที่',
        'siblings_studying' => 'จำนวนพี่น้องที่กำลังศึกษา',
    ];

    /** ระยะทางจากบ้านถึงที่รับบริการ (กม.) */
    public const TRAVEL_FIELDS = [
        'travel_km_unpaved' => 'ถนนลูกรัง',
        'travel_km_paved' => 'ถนนลาดยาง',
        'travel_km_waterway' => 'ทางน้ำ',
    ];

    /** วันล่วงหน้าที่เตือนบัตรประจำตัวคนพิการใกล้หมดอายุ */
    public const CARD_EXPIRY_WARN_DAYS = 90;

    public const ID_TYPES = [
        'citizen' => 'เลขประจำตัวประชาชน',
        'g_code' => 'เลข G (ไม่มีเลขประจำตัวประชาชน)',
        'passport' => 'หนังสือเดินทาง (Passport)',
        'none' => 'ยังไม่มีเอกสารแสดงตน',
    ];

    public const TITLES = ['เด็กชาย', 'เด็กหญิง', 'นาย', 'นางสาว'];

    public const GENDERS = ['male' => 'ชาย', 'female' => 'หญิง'];

    /** ประเภทการรับบริการ => [ชื่อ, กลุ่ม center|outreach] */
    public const SERVICE_TYPES = [
        'center_boarding' => ['ประจำ (ในศูนย์)', 'center'],
        'center_day' => ['ไป-กลับ (ในศูนย์)', 'center'],
        'service_unit' => ['ที่หน่วยบริการ', 'outreach'],
        'home' => ['ให้บริการที่บ้าน', 'outreach'],
        'hospital' => ['ศูนย์การเรียนสำหรับเด็กในโรงพยาบาล', 'outreach'],
        'learning_center' => ['ศูนย์การเรียนเฉพาะความพิการ', 'outreach'],
    ];

    public const SERVICE_GROUPS = ['center' => 'รับบริการที่ศูนย์', 'outreach' => 'รับบริการนอกศูนย์'];

    /** ชื่อสั้นในช่องเลือกแบบ 2 ขั้น (เลือกกลุ่มก่อน แล้วเลือกประเภท) */
    public const SERVICE_TYPE_SHORT = [
        'center_boarding' => 'ประจำ',
        'center_day' => 'ไป-กลับ',
    ];

    /** ชื่อเดิม/ชื่ออื่นที่ยังรับได้ตอนนำเข้า CSV => service_type */
    public const SERVICE_TYPE_ALIASES = [
        'หน่วยบริการ' => 'service_unit',
        'เจ็บป่วยเรื้อรังในโรงพยาบาล' => 'hospital',
        'ประจำ' => 'center_boarding',
        'ไป-กลับ' => 'center_day',
    ];

    public static function serviceTypeShort(string $type): string
    {
        return self::SERVICE_TYPE_SHORT[$type] ?? (self::SERVICE_TYPES[$type][0] ?? $type);
    }

    public const PARENTS_STATUSES = [
        'together' => 'อยู่ร่วมกัน',
        'separated' => 'แยกกันอยู่ (ไม่หย่า)',
        'divorced' => 'หย่าร้าง',
        'father_remarried' => 'บิดาสมรสใหม่',
        'mother_remarried' => 'มารดาสมรสใหม่',
        'father_deceased' => 'บิดาเสียชีวิต',
        'mother_deceased' => 'มารดาเสียชีวิต',
        'other' => 'อื่น ๆ',
    ];

    public const LIVES_WITH = ['parents' => 'บิดา-มารดา', 'father' => 'บิดา', 'mother' => 'มารดา', 'guardian' => 'ผู้ปกครอง'];

    public const RELATIONS = ['father' => 'บิดา', 'mother' => 'มารดา', 'guardian' => 'ผู้ปกครอง'];

    public const LIFE_STATUSES = ['alive' => 'มีชีวิต', 'deceased' => 'เสียชีวิต'];

    /** ประเภทความพิการ 9 ประเภท (ประกาศกระทรวงศึกษาธิการ) */
    public const DISABILITY_TYPES = [
        'visual' => 'บกพร่องทางการเห็น',
        'hearing' => 'บกพร่องทางการได้ยิน',
        'intellectual' => 'บกพร่องทางสติปัญญา',
        'physical_health' => 'บกพร่องทางร่างกายหรือการเคลื่อนไหวหรือสุขภาพ',
        'learning' => 'บกพร่องทางการเรียนรู้',
        'speech_language' => 'บกพร่องทางการพูดและภาษา',
        'behavioral_emotional' => 'บกพร่องทางพฤติกรรมหรืออารมณ์',
        'autism' => 'บุคคลออทิสติก',
        'multiple' => 'บุคคลพิการซ้อน',
    ];

    /** ระดับความพิการ (เฉพาะการเห็น/การได้ยิน) */
    public const DISABILITY_LEVELS = [
        'blind' => ['ตาบอด', 'visual'],
        'low_vision' => ['สายตาเลือนราง', 'visual'],
        'deaf' => ['หูหนวก', 'hearing'],
        'hard_of_hearing' => ['หูตึง', 'hearing'],
    ];

    public const SCREENING_TYPES = [
        'none' => 'ไม่มีใบรับรองแพทย์/ไม่มีบัตรประจำตัวคนพิการ',
        'medical_cert' => 'มีใบรับรองแพทย์',
        'disability_card' => 'มีบัตรประจำตัวคนพิการ',
    ];

    /** ช่องที่กรอกได้ตามประเภทผู้คัดกรอง (ช่องของประเภทอื่นถูกล้างเมื่อบันทึก) */
    public const SCREENING_FIELDS = [
        'none' => ['screener_employee_id'],
        'medical_cert' => ['hospital', 'doctor', 'examined_at'],
        'disability_card' => ['card_types', 'card_issued_at', 'card_expires_at'],
    ];

    /** ประเภทตามบัตรประจำตัวคนพิการ (พ.ร.บ.ส่งเสริมและพัฒนาคุณภาพชีวิตคนพิการ) */
    public const CARD_TYPES = [
        'visual' => 'ความพิการทางการเห็น',
        'hearing' => 'ความพิการทางการได้ยินหรือสื่อความหมาย',
        'physical' => 'ความพิการทางการเคลื่อนไหวหรือทางร่างกาย',
        'mental' => 'ความพิการทางจิตใจหรือพฤติกรรม',
        'intellectual' => 'ความพิการทางสติปัญญา',
        'learning' => 'ความพิการทางการเรียนรู้',
        'autism' => 'ความพิการทางออทิสติก',
    ];

    public const BLOOD_GROUPS = ['unknown' => 'ไม่ทราบ', 'A' => 'A', 'B' => 'B', 'AB' => 'AB', 'O' => 'O'];

    /**
     * ประเภทเอกสารแนบ => [ชื่อ, เป็นข้อมูลอ่อนไหวหรือไม่]
     * เอกสารทางการแพทย์/ความพิการต้องมีสิทธิ์ดูข้อมูลอ่อนไหวจึงจะเปิดได้
     */
    public const DOCUMENT_TYPES = [
        'photo' => ['รูปถ่ายนักเรียน', false],
        'application' => ['ใบสมัคร/แบบสอบถามประวัติ', false],
        'birth_certificate' => ['สูติบัตร', false],
        'id_card' => ['บัตรประจำตัวประชาชนนักเรียน', false],
        'house_registration' => ['ทะเบียนบ้านนักเรียน', false],
        'gcode' => ['เอกสารเลข G (PDF จาก สพฐ.)', false],
        'name_change' => ['เอกสารเปลี่ยนชื่อ-นามสกุล', false],
        'school_record' => ['ผลการเรียน/ผลการส่งต่อ', false],
        'guardian_documents' => ['เอกสารบิดา มารดา ผู้ปกครอง', false],
        'disability_card' => ['บัตรประจำตัวคนพิการ', true],
        'medical_certificate' => ['เอกสารรับรองความพิการจากแพทย์', true],
        'disability_registry' => ['สมุดจดทะเบียนคนพิการ', true],
        'iq_test' => ['ผลตรวจวัดระดับเชาวน์ปัญญา (IQ)', true],
        'medical_result' => ['ผลการรักษาแพทย์', true],
        'hearing_test' => ['ผลตรวจวัดระดับการได้ยิน', true],
        'mother_child_book' => ['สมุดแม่และเด็ก', true],
        'other' => ['เอกสารอื่น ๆ', false],
    ];

    public const REF_TYPE = 'academic_students';
    public const REF_TYPE_SENSITIVE = 'academic_students_sensitive';

    /** รายการเดียวกับระดับชั้นของห้องบริการ */
    public const GRADE_LEVELS = ServiceRoomService::GRADE_LEVELS;

    public const NATIONALITIES = ['ไทย', 'รอให้สัญชาติไทย', 'ไม่มีสัญชาติ', 'บุคคลที่ไม่มีสถานะทางทะเบียน', 'พม่า', 'ลาว', 'จีน', 'กัมพูชา', 'เวียดนาม'];

    public const RELIGIONS = ['พุทธ', 'คริสต์', 'อิสลาม', 'พราหมณ์-ฮินดู', 'อื่น ๆ'];

    public const TRIBES = ['พื้นราบ', 'ม้ง', 'เมี่ยน', 'ลีซู', 'ลาหู่', 'อาข่า', 'กะเหรี่ยง', 'ลัวะ', 'ขมุ', 'มลาบรี', 'ไทลื้อ', 'ไทใหญ่', 'จีนฮ่อ'];

    public const OCCUPATIONS = [
        'เกษตรกรรม', 'รับจ้าง', 'ค้าขาย/ธุรกิจส่วนตัว', 'รับราชการ', 'พนักงานรัฐวิสาหกิจ', 'พนักงานบริษัทเอกชน',
        'ข้าราชการ/พนักงานของรัฐเกษียณ', 'พระ/นักบวช', 'ไม่ได้ประกอบอาชีพ', 'อื่น ๆ',
    ];

    public const PROVINCES = [
        'กรุงเทพมหานคร', 'กระบี่', 'กาญจนบุรี', 'กาฬสินธุ์', 'กำแพงเพชร', 'ขอนแก่น', 'จันทบุรี', 'ฉะเชิงเทรา', 'ชลบุรี', 'ชัยนาท',
        'ชัยภูมิ', 'ชุมพร', 'เชียงราย', 'เชียงใหม่', 'ตรัง', 'ตราด', 'ตาก', 'นครนายก', 'นครปฐม', 'นครพนม', 'นครราชสีมา',
        'นครศรีธรรมราช', 'นครสวรรค์', 'นนทบุรี', 'นราธิวาส', 'น่าน', 'บึงกาฬ', 'บุรีรัมย์', 'ปทุมธานี', 'ประจวบคีรีขันธ์',
        'ปราจีนบุรี', 'ปัตตานี', 'พระนครศรีอยุธยา', 'พะเยา', 'พังงา', 'พัทลุง', 'พิจิตร', 'พิษณุโลก', 'เพชรบุรี', 'เพชรบูรณ์',
        'แพร่', 'ภูเก็ต', 'มหาสารคาม', 'มุกดาหาร', 'แม่ฮ่องสอน', 'ยโสธร', 'ยะลา', 'ร้อยเอ็ด', 'ระนอง', 'ระยอง', 'ราชบุรี',
        'ลพบุรี', 'ลำปาง', 'ลำพูน', 'เลย', 'ศรีสะเกษ', 'สกลนคร', 'สงขลา', 'สตูล', 'สมุทรปราการ', 'สมุทรสงคราม', 'สมุทรสาคร',
        'สระแก้ว', 'สระบุรี', 'สิงห์บุรี', 'สุโขทัย', 'สุพรรณบุรี', 'สุราษฎร์ธานี', 'สุรินทร์', 'หนองคาย', 'หนองบัวลำภู',
        'อ่างทอง', 'อำนาจเจริญ', 'อุดรธานี', 'อุตรดิตถ์', 'อุทัยธานี', 'อุบลราชธานี',
    ];

    /** ช่องที่อยู่ (เก็บเป็น JSON) => [ชื่อ, ความยาวสูงสุด] */
    public const ADDRESS_FIELDS = [
        'house_code' => ['รหัสประจำบ้าน', 11],
        'house_no' => ['บ้านเลขที่', 20],
        'moo' => ['หมู่ที่', 5],
        'village' => ['หมู่บ้าน', 100],
        'soi' => ['ซอย', 100],
        'road' => ['ถนน', 100],
        'subdistrict' => ['ตำบล/แขวง', 100],
        'district' => ['อำเภอ/เขต', 100],
        'province' => ['จังหวัด', 100],
        'postcode' => ['รหัสไปรษณีย์', 5],
    ];

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status][0] ?? $status;
    }

    /** ระดับชั้นถัดไปตาม GRADE_LEVELS (ระดับสุดท้าย/ไม่อยู่ในรายการ = คงเดิม) */
    public static function nextGrade(?string $grade): ?string
    {
        if ($grade === null || $grade === '') {
            return null;
        }
        $i = array_search($grade, self::GRADE_LEVELS, true);

        return $i === false ? $grade : (self::GRADE_LEVELS[$i + 1] ?? $grade);
    }

    public static function serviceLabel(?string $type): string
    {
        return $type !== null ? (self::SERVICE_TYPES[$type][0] ?? $type) : '-';
    }

    /** ที่อยู่ JSON → ข้อความบรรทัดเดียว */
    public static function formatAddress(?array $a): string
    {
        if ($a === null || array_filter($a) === []) {
            return '-';
        }
        $parts = [];
        if (!empty($a['house_no'])) {
            $parts[] = 'บ้านเลขที่ ' . $a['house_no'];
        }
        if (!empty($a['moo'])) {
            $parts[] = 'หมู่ ' . $a['moo'];
        }
        foreach (['village' => '', 'soi' => 'ซ.', 'road' => 'ถ.', 'subdistrict' => 'ต.', 'district' => 'อ.', 'province' => 'จ.'] as $k => $prefix) {
            if (!empty($a[$k])) {
                $parts[] = $prefix . $a[$k];
            }
        }
        if (!empty($a['postcode'])) {
            $parts[] = $a['postcode'];
        }

        return implode(' ', $parts);
    }

    /** อายุ "x ปี y เดือน" */
    public static function age(?string $birthDate, ?string $at = null): string
    {
        if ($birthDate === null || $birthDate === '') {
            return '-';
        }
        $diff = (new \DateTimeImmutable($birthDate))->diff(new \DateTimeImmutable($at ?? 'today'));

        return $diff->invert ? '-' : "{$diff->y} ปี {$diff->m} เดือน";
    }
}
