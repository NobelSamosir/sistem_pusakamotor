# Uji cepat REST API Pusaka Motor (hanya membaca data, tidak mengubah apa pun)
param(
  [string]$Base = "http://127.0.0.1:8000/api",
  [string]$Email = "pemilik@pusakamotor.test",
  [string]$Password = "PusakaMotor#2026"
)

$body = @{ email = $Email; password = $Password } | ConvertTo-Json
$login = Invoke-RestMethod -Method Post -Uri "$Base/login" -Body $body -ContentType "application/json" -Headers @{ Accept = "application/json" }
$h = @{ Authorization = "Bearer $($login.token)"; Accept = "application/json" }
Write-Host "[OK]    POST /api/login  (masuk sebagai $($login.user.name))" -ForegroundColor Green

$daftar = @("me", "dashboard", "accounts", "suppliers", "customers", "motorcycles", "purchases", "sales",
            "journals", "reports/stock", "reports/purchases-sales", "reports/income-statement",
            "reports/balance-sheet", "reports/cash-flow")
$gagal = 0
foreach ($p in $daftar) {
  try {
    $null = Invoke-RestMethod -Uri "$Base/$p" -Headers $h
    Write-Host "[OK]    GET  /api/$p" -ForegroundColor Green
  } catch {
    $gagal++
    Write-Host "[GAGAL] GET  /api/$p : $($_.Exception.Message)" -ForegroundColor Red
  }
}

$akun = Invoke-RestMethod -Uri "$Base/accounts" -Headers $h
Write-Host "Jumlah akun: $($akun.Count) (harus 6)"
$neraca = Invoke-RestMethod -Uri "$Base/reports/balance-sheet" -Headers $h
Write-Host "Neraca seimbang: $($neraca.is_balanced) (harus True)"

try {
  $null = Invoke-RestMethod -Uri "$Base/dashboard" -Headers @{ Accept = "application/json" }
  Write-Host "[GAGAL] /api/dashboard bisa diakses tanpa login" -ForegroundColor Red; $gagal++
} catch {
  Write-Host "[OK]    Tanpa token ditolak (401)" -ForegroundColor Green
}

Invoke-RestMethod -Method Post -Uri "$Base/logout" -Headers $h | Out-Null
Write-Host "[OK]    POST /api/logout" -ForegroundColor Green
if ($gagal -eq 0) { Write-Host "`nSemua endpoint berjalan." -ForegroundColor Green } else { Write-Host "`n$gagal endpoint gagal." -ForegroundColor Red }