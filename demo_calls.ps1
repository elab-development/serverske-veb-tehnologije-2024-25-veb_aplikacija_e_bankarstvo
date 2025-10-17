# demo_calls.ps1
# Pokretanje:  Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass ; .\demo_calls.ps1

$baseUrl = "http://127.0.0.1:8000"

function Title($t) { Write-Host "`n==== $t ====" -ForegroundColor Cyan }

# -- 0) Token: koristi TVOJ token; ako ne važi, login kao admin@example.com/password
$token = "2|YrhdIav9lCBh9604ZPV3H2BcChulbkuwAnxLXrI862d85abc"

function Ensure-Token {
  param([ref]$tokenRef)

  if ([string]::IsNullOrWhiteSpace($tokenRef.Value)) {
    Title "Login (admin@example.com)"
    $loginBody = @{ email="admin@example.com"; password="password" } | ConvertTo-Json
    try {
      $resp = Invoke-RestMethod -Method Post -Uri "$baseUrl/api/login" -Headers @{ "Content-Type"="application/json" } -Body $loginBody
      $tokenRef.Value = $resp.token
      Write-Host "Token (login): $($tokenRef.Value)" -ForegroundColor Green
    } catch {
      Write-Host "Login failed." -ForegroundColor Red
      $_ | Out-String | Write-Host
      exit 1
    }
    return
  }

  # Proveri da li radi postojeći token
  try {
    Invoke-RestMethod -Method Get -Uri "$baseUrl/api/accounts" -Headers @{ Authorization = "Bearer $($tokenRef.Value)"; Accept="application/json" } -ErrorAction Stop | Out-Null
    Write-Host "Token (preset) OK." -ForegroundColor Green
  } catch {
    Title "Preset token ne radi → login"
    $loginBody = @{ email="admin@example.com"; password="password" } | ConvertTo-Json
    try {
      $resp = Invoke-RestMethod -Method Post -Uri "$baseUrl/api/login" -Headers @{ "Content-Type"="application/json" } -Body $loginBody
      $tokenRef.Value = $resp.token
      Write-Host "Token (novi): $($tokenRef.Value)" -ForegroundColor Green
    } catch {
      Write-Host "Login failed." -ForegroundColor Red
      $_ | Out-String | Write-Host
      exit 1
    }
  }
}

# -- 1) Obavezno: osiguraj token
Ensure-Token ([ref]$token)

# -- 2) GET /api/accounts
Title "GET /api/accounts"
try {
  $accounts = Invoke-RestMethod -Method Get -Uri "$baseUrl/api/accounts" -Headers @{ Authorization = "Bearer $token"; Accept="application/json" }
  $accounts | ConvertTo-Json -Depth 5 | Write-Host
} catch {
  Write-Host "Greška /api/accounts:" -ForegroundColor Yellow
  $_ | Out-String | Write-Host
}

# -- 3) Odaberi 2 RSD naloga za transfer (dinamički)
$rsdAccounts = @()
try {
  $rsdAccounts = $accounts | Where-Object { $_.currency -eq "RSD" } | Select-Object -First 2
  if ($rsdAccounts.Count -lt 2) { throw "Nema dovoljno RSD računa za transfer (trebaju 2)." }
  Write-Host ("RSD nalozi za transfer: " + ($rsdAccounts | ForEach-Object { $_.id }) -join ", ")
} catch {
  Write-Host $_ -ForegroundColor Red
  # Ako nema 2 RSD naloga, napravi jedan random pa opet učitaj
  Title "POST /api/accounts (kreiranje RSD naloga, random IBAN)"
  $rand = Get-Random -Minimum 1000 -Maximum 9999
  $accBody = @{ iban = "RS35PS$rand$rand"; currency = "RSD"; balance = 10000 } | ConvertTo-Json
  try {
    Invoke-RestMethod -Method Post -Uri "$baseUrl/api/accounts" -Headers @{ Authorization = "Bearer $token"; "Content-Type"="application/json" } -Body $accBody | Out-Null
    $accounts = Invoke-RestMethod -Method Get -Uri "$baseUrl/api/accounts" -Headers @{ Authorization = "Bearer $token"; Accept="application/json" }
    $rsdAccounts = $accounts | Where-Object { $_.currency -eq "RSD" } | Select-Object -First 2
    Write-Host ("RSD nalozi za transfer: " + ($rsdAccounts | ForEach-Object { $_.id }) -join ", ")
  } catch {
    Write-Host "Kreiranje naloga nije uspelo." -ForegroundColor Red
    $_ | Out-String | Write-Host
    exit 1
  }
}

$srcId = $rsdAccounts[0].id
$dstId = $rsdAccounts[1].id

# -- 4) POST /api/exchange-rates/sync (ručni rate – uvek radi)
Title "POST /api/exchange-rates/sync (ručni rate)"
$today = Get-Date -Format "yyyy-MM-dd"
$fxBody = @{ base="RSD"; quote="EUR"; date=$today; rate=0.0085 } | ConvertTo-Json
try {
  $fx = Invoke-RestMethod -Method Post -Uri "$baseUrl/api/exchange-rates/sync" -Headers @{ Authorization = "Bearer $token"; "Content-Type"="application/json" } -Body $fxBody
  $fx | ConvertTo-Json -Depth 5 | Write-Host
} catch {
  Write-Host "FX sync nije prošao (pokušaću bez rate):" -ForegroundColor Yellow
  $_ | Out-String | Write-Host
  # Pokušaj bez rate (možda rade fallbackovi)
  try {
    $fxBody2 = @{ base="RSD"; quote="EUR"; date=$today } | ConvertTo-Json
    $fx2 = Invoke-RestMethod -Method Post -Uri "$baseUrl/api/exchange-rates/sync" -Headers @{ Authorization = "Bearer $token"; "Content-Type"="application/json" } -Body $fxBody2
    $fx2 | ConvertTo-Json -Depth 5 | Write-Host
  } catch {
    Write-Host "Ni fallback nije uspeo; nastavljam dalje (nije blokirajuće za RSD->RSD transfer)." -ForegroundColor Yellow
  }
}

# -- 5) POST /api/transfers (RSD -> RSD)
Title "POST /api/transfers (RSD -> RSD)"
$trBody = @{ source_account_id=$srcId; target_account_id=$dstId; amount=1200; title="Prenos demo" } | ConvertTo-Json
try {
  $tr = Invoke-RestMethod -Method Post -Uri "$baseUrl/api/transfers" -Headers @{ Authorization = "Bearer $token"; "Content-Type"="application/json" } -Body $trBody
  $tr | ConvertTo-Json -Depth 6 | Write-Host
} catch {
  Write-Host "Transfer nije uspeo (proveri ID-ove računa i stanje):" -ForegroundColor Yellow
  $_ | Out-String | Write-Host
}

# -- 6) GET /api/transactions (bez filtera; možeš dodati ?q=, &category_id=, &from=&to= )
Title "GET /api/transactions"
try {
  $tx = Invoke-RestMethod -Method Get -Uri "$baseUrl/api/transactions" -Headers @{ Authorization = "Bearer $token"; Accept="application/json" }
  $tx | ConvertTo-Json -Depth 6 | Write-Host
} catch {
  Write-Host "Transactions GET nije uspeo:" -ForegroundColor Yellow
  $_ | Out-String | Write-Host
}

Write-Host "`nGOTOVO" -ForegroundColor Green
