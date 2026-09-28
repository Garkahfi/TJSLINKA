[CmdletBinding()]
param(
    [switch] $Full
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

$uatTests = @(
    'tests/Feature/UatFoundationTest.php',
    'tests/Feature/AdminAuthenticationTest.php',
    'tests/Feature/PublicAuthenticationTest.php',
    'tests/Feature/PumkAdminAuthenticationTest.php',
    'tests/Feature/LoginSecurityTest.php',
    'tests/Feature/SuperAdminPhaseThreeTest.php',
    'tests/Feature/SuperAdminUserManagementTest.php',
    'tests/Feature/PublicHomeDashboardTest.php',
    'tests/Feature/DashboardReferenceDataTest.php',
    'tests/Feature/TerasTjslManagementTest.php',
    'tests/Feature/ProgramArchiveMonitoringTest.php',
    'tests/Feature/ProgramRincianTujuanTest.php',
    'tests/Feature/ProgramCooperationTypeTest.php',
    'tests/Feature/ProgramTwoPhaseWorkflowTest.php',
    'tests/Feature/AdminPhaseTwoTest.php',
    'tests/Feature/SubmissionWorkflowFixTest.php',
    'tests/Feature/BantuanCsrTwoPhaseWorkflowTest.php',
    'tests/Feature/BantuanCsrOverviewIntegrationTest.php',
    'tests/Feature/BantuanCsrPillarGroupingTest.php',
    'tests/Feature/TjslStatusFilterTest.php',
    'tests/Feature/PumkDatabaseSchemaTest.php',
    'tests/Feature/PumkImportServiceTest.php',
    'tests/Feature/PumkMitraManagementTest.php',
    'tests/Feature/PumkPaymentProofTest.php',
    'tests/Feature/PumkYearAndContractDocumentTest.php',
    'tests/Feature/PumkLoanCompletionTest.php',
    'tests/Feature/PumkMonitoringDashboardTest.php',
    'tests/Feature/SuperAdminPumkMonitoringTest.php',
    'tests/Feature/MonitoringUploadAccessTest.php',
    'tests/Feature/MonitoringExcelImportTest.php',
    'tests/Unit/KartuPiutangServiceTest.php',
    'tests/Unit/PumkPiutangCalculatorTest.php'
)

Push-Location $projectRoot

try {
    Write-Host 'TJSLINKA - UAT otomatis' -ForegroundColor Cyan
    Write-Host 'Database pengujian: SQLite in-memory (data aplikasi tidak diubah).'

    & php artisan config:clear --env=testing
    if ($LASTEXITCODE -ne 0) {
        throw 'Gagal membersihkan cache konfigurasi testing.'
    }

    if ($Full) {
        Write-Host 'Menjalankan seluruh regression test...' -ForegroundColor Yellow
        & php artisan test
    } else {
        Write-Host "Menjalankan paket UAT terfokus ($($uatTests.Count) file)..." -ForegroundColor Yellow
        & php artisan test @uatTests
    }

    if ($LASTEXITCODE -ne 0) {
        throw "UAT otomatis gagal dengan exit code $LASTEXITCODE."
    }

    Write-Host 'UAT otomatis LULUS.' -ForegroundColor Green
} finally {
    Pop-Location
}
