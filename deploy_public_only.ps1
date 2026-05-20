# public_html only deploy (re-run after path fix)
$ErrorActionPreference = 'Stop'
$ftpHost = 'www311.onamae.ne.jp'
$ftpUser = 'date1@ponnu.net'
$ftpPass = 'Ponnu17&'
$cred = "${ftpUser}:${ftpPass}"
$localBase = 'C:/Users/matsuyama/projects/ponnu/public_html'
$remote    = 'ic.ponnu.net'

$files = Get-ChildItem -Recurse -File $localBase
$localPrefix = (Resolve-Path $localBase).Path
$total = $files.Count; $ok = 0; $fail = 0; $i = 0
foreach ($f in $files) {
    $i++
    $rel = $f.FullName.Substring($localPrefix.Length).TrimStart('\','/') -replace '\\','/'
    $remotePath = "ftp://$ftpHost/$remote/$rel"
    $result = & curl.exe -s -S --connect-timeout 15 --max-time 60 --ftp-create-dirs --user $cred -T $f.FullName $remotePath 2>&1
    if ($LASTEXITCODE -eq 0) { $ok++ } else { $fail++; Write-Host "[FAIL] $rel  -> $result" -ForegroundColor Red }
    if ($i % 10 -eq 0) { Write-Host "Progress: $i/$total (ok=$ok fail=$fail)" }
}
Write-Host ""
Write-Host "Done. total=$total ok=$ok fail=$fail"
