<#
  Nirog Bhumi Academy — turn a lesson video into protected (encrypted) HLS on Windows.

  Usage (in PowerShell, in the folder that holds the video):
    .\encode-lesson.ps1 "Week 1 - Breath awareness.mp4"
    .\encode-lesson.ps1 "Yoga Nidra.mp3" -Audio
    .\encode-lesson.ps1 input.mp4 -Name week1-breath

  Needs ffmpeg (free): install with  winget install Gyan.FFmpeg
  If PowerShell refuses to run the script, run once:
    Set-ExecutionPolicy -Scope CurrentUser RemoteSigned

  Output: a folder with master.m3u8, one sub-folder per quality and enc.key.
  Upload the whole folder to wp-content/uploads/nirog-academy/media/ and press
  Academy → Import Videos → Find new videos.
#>
param(
  [Parameter(Mandatory = $true, Position = 0)][string]$InputFile,
  [string]$Name = "",
  [switch]$Audio
)
$ErrorActionPreference = "Stop"

if (-not (Test-Path $InputFile)) { throw "File not found: $InputFile" }
if (-not (Get-Command ffmpeg -ErrorAction SilentlyContinue)) { throw "ffmpeg is not installed. Run: winget install Gyan.FFmpeg" }

if ($Name -eq "") {
  $Name = [IO.Path]::GetFileNameWithoutExtension($InputFile).ToLower() -replace '[^a-z0-9]+', '-'
  $Name = $Name.Trim('-')
}
if ($Name -notmatch '^[a-z0-9_-]+$') { throw "Folder name may only use a-z, 0-9, - and _." }
if (Test-Path $Name) { throw "A folder called '$Name' already exists. Delete it or use -Name." }
New-Item -ItemType Directory -Path $Name | Out-Null
$Out = (Resolve-Path $Name).Path

# A fresh random 128-bit key and IV for this lesson.
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$key = New-Object byte[] 16; $rng.GetBytes($key)
$iv = New-Object byte[] 16; $rng.GetBytes($iv)
[IO.File]::WriteAllBytes((Join-Path $Out "enc.key"), $key)
$ivHex = ($iv | ForEach-Object { $_.ToString("x2") }) -join ""
$keyInfo = [IO.Path]::GetTempFileName()
$keyPath = (Join-Path $Out "enc.key") -replace '\\', '/'
[IO.File]::WriteAllText($keyInfo, "key://lesson`n$keyPath`n$ivHex`n")

$common = @("-hls_time", "6", "-hls_playlist_type", "vod", "-hls_flags", "single_file", "-hls_key_info_file", $keyInfo, "-master_pl_name", "master.m3u8")
$segment = (Join-Path $Out "%v/media.ts") -replace '\\', '/'
$playlist = (Join-Path $Out "%v/index.m3u8") -replace '\\', '/'

try {
  if ($Audio) {
    & ffmpeg -hide_banner -loglevel warning -stats -i $InputFile -vn -map 0:a:0 -c:a aac -b:a 96k -ac 2 `
      -f hls @common -var_stream_map "a:0,name:audio" -hls_segment_filename $segment $playlist
  } else {
    & ffmpeg -hide_banner -loglevel warning -stats -i $InputFile `
      -filter_complex "[0:v]split=3[v1][v2][v3];[v1]scale=-2:360[v360];[v2]scale=-2:540[v540];[v3]scale=-2:720[v720]" `
      -map "[v360]" -map 0:a:0 -map "[v540]" -map 0:a:0 -map "[v720]" -map 0:a:0 `
      -c:v libx264 -preset medium -profile:v main -pix_fmt yuv420p -sc_threshold 0 `
      -force_key_frames "expr:gte(t,n_forced*2)" `
      -b:v:0 700k -maxrate:v:0 770k -bufsize:v:0 1400k `
      -b:v:1 1400k -maxrate:v:1 1540k -bufsize:v:1 2800k `
      -b:v:2 2500k -maxrate:v:2 2750k -bufsize:v:2 5000k `
      -c:a aac -b:a 96k -ac 2 `
      -f hls @common -var_stream_map "v:0,a:0,name:360p v:1,a:1,name:540p v:2,a:2,name:720p" `
      -hls_segment_filename $segment $playlist
  }
  if ($LASTEXITCODE -ne 0) { throw "ffmpeg failed." }
} finally {
  Remove-Item $keyInfo -ErrorAction SilentlyContinue
}

Get-ChildItem -Path $Out -Recurse -Filter *.tmp | Remove-Item
Write-Host ""
Write-Host "Done: $Name\"
Write-Host "Next: upload the folder '$Name' to wp-content/uploads/nirog-academy/media/"
Write-Host "      then open Academy -> Import Videos -> Find new videos."
