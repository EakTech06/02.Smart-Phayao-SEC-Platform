<?php

/**
 * ท้ายกระดาษรายงานที่พิมพ์ (ใช้ร่วมทุกหน้ารายงาน): View::template('print_footer')
 * ตอนพิมพ์แสดงที่ขอบล่างของทุกหน้าผ่าน @page margin box (Chrome/Edge) พร้อมเลขหน้า
 * บนจอแสดงเป็นบรรทัดท้ายรายงาน (ซ่อนตอนพิมพ์เพื่อไม่ให้ซ้ำ)
 */

$text = print_footer_text();
$css = static fn (string $s): string => '"' . str_replace(['\\', '"', "\n", "\r", '<'], ['\\\\', '\\"', ' ', ' ', '\\3C '], $s) . '"';
?>
<style>
  @media print {
    @page {
      margin-bottom: 18mm;
      @bottom-left { content: <?= $css($text) ?>; font-family: system-ui, "Segoe UI", Tahoma, sans-serif; font-size: 8.5pt; color: #555; }
      @bottom-right { content: "หน้า " counter(page) " / " counter(pages); font-family: system-ui, "Segoe UI", Tahoma, sans-serif; font-size: 8.5pt; color: #555; }
    }
  }
</style>
<div class="small text-muted border-top pt-2 mt-3 d-print-none print-footer-preview"><i class="bi bi-printer me-1"></i><?= e($text) ?></div>
