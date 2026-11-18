<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Audit;
use Core\Controller;
use Core\Response;
use Core\Setting;
use Core\XlsxWriter;
use Modules\CoreAdmin\Services\ReportService;

/** Phase 4: รายงานภาพรวมของหน่วยงาน (ดูบนจอ / พิมพ์ A4 / ส่งออก Excel) */
final class ReportController extends Controller
{
    public function overview(): Response
    {
        [$report, $filters] = $this->build();

        return $this->view('core_admin::reports/overview', [
            'title' => 'รายงานภาพรวมหน่วยงาน',
            'report' => $report,
            'filters' => $filters,
            'periodOptions' => ReportService::periodOptions(),
            'departmentOptions' => ReportService::departments(ReportService::scopeDepartmentIds()),
            'orgName' => (string) Setting::get('org.name', config('app.org')),
        ]);
    }

    public function export(): Response
    {
        [$report, $filters] = $this->build();
        $orgName = (string) Setting::get('org.name', config('app.org'));

        $xlsx = new XlsxWriter();
        foreach (ReportService::toSheets($report, $orgName) as $name => $rows) {
            $xlsx->addSheet($name, $rows);
        }
        $content = $xlsx->build();

        Audit::log('report.export', 'core', 'report', 'overview', null, [
            'period' => $report['period']['from'] . '..' . $report['period']['to'],
            'department_id' => $filters['department_id'],
            'format' => 'xlsx',
        ]);

        $name = 'รายงานภาพรวม_' . $report['period']['from'] . '_' . $report['period']['to'] . '.xlsx';

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => 'attachment; filename="overview-report-' . date('Ymd') . ".xlsx\"; filename*=UTF-8''" . rawurlencode($name),
        ]);
    }

    /** @return array{0: array, 1: array{period:string, from:string, to:string, department_id:?int}} */
    private function build(): array
    {
        $scope = ReportService::scopeDepartmentIds();
        $departmentId = (int) $this->request->query('department_id', 0);
        $departmentIds = ReportService::narrowScope($scope, $departmentId > 0 ? $departmentId : null);

        $period = ReportService::resolvePeriod(
            (string) $this->request->query('period', ''),
            self::str($this->request->query('from')),
            self::str($this->request->query('to'))
        );

        return [
            ReportService::overview($period, $departmentIds),
            [
                'period' => $period['period'],
                'from' => $period['from'],
                'to' => $period['to'],
                'department_id' => $departmentId > 0 ? $departmentId : null,
            ],
        ];
    }

    private static function str(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
