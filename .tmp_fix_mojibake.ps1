Set-Location 'c:/xampp/htdocs/ems'
$exclude = @('vendor\\','assets\\vendor\\','node_modules\\','.git\\')
$extRegex = '^\.(php|html|htm|css|js|md|txt)$'
$markerRegex = 'Ø|Ù|Ã|Â|â|â|âœ|âƒ'
$cp1252 = [System.Text.Encoding]::GetEncoding(1252)
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
function Count-Arabic([string]$s) { ([regex]::Matches($s, '[\u0600-\u06FF]')).Count }
$files = Get-ChildItem -Recurse -File | Where-Object { $_.Extension -match $extRegex } | Where-Object { $p=$_.FullName; -not ($exclude | ForEach-Object { $p -match $_ } | Where-Object { $_ }) } | Where-Object { Select-String -Path $_.FullName -Pattern $markerRegex -Quiet }
$changed=@(); $skipped=@()
foreach($f in $files){
  try {
    $rawBytes=[System.IO.File]::ReadAllBytes($f.FullName)
    $text=[System.Text.Encoding]::UTF8.GetString($rawBytes)
    $beforeMarkers=([regex]::Matches($text,$markerRegex)).Count
    if($beforeMarkers -eq 0){ continue }
    $candidate=[System.Text.Encoding]::UTF8.GetString($cp1252.GetBytes($text))
    $afterMarkers=([regex]::Matches($candidate,$markerRegex)).Count
    $beforeArabic=Count-Arabic $text
    $afterArabic=Count-Arabic $candidate
    $hasReplacement=$candidate.Contains([char]0xFFFD)
    if(($afterMarkers -lt $beforeMarkers) -and (-not $hasReplacement) -and ($afterArabic -ge $beforeArabic)){
      [System.IO.File]::WriteAllText($f.FullName,$candidate,$utf8NoBom)
      $changed += [pscustomobject]@{File=$f.FullName;Before=$beforeMarkers;After=$afterMarkers}
    } else {
      $skipped += [pscustomobject]@{File=$f.FullName;Before=$beforeMarkers;After=$afterMarkers}
    }
  } catch {
    $skipped += [pscustomobject]@{File=$f.FullName;Error=$_.Exception.Message}
  }
}
"CHANGED=$($changed.Count)"
$changed | Sort-Object File | ForEach-Object { "- " + $_.File.Replace((Get-Location).Path + '\\', '') + " [" + $_.Before + " -> " + $_.After + "]" }
"SKIPPED=$($skipped.Count)"
