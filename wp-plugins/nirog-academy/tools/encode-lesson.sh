#!/usr/bin/env bash
# Nirog Bhumi Academy — turn a lesson video into protected (encrypted) HLS.
#
# Usage:
#   ./encode-lesson.sh "Week 1 - Breath awareness.mp4"
#   ./encode-lesson.sh --audio "Yoga Nidra.mp3"        # audio-only lesson
#   ./encode-lesson.sh --name week1-breath input.mp4   # choose the folder name
#
# Output: a folder (for example week-1-breath-awareness/) containing
#   master.m3u8, one sub-folder per quality, and enc.key.
# Upload the whole folder to wp-content/uploads/nirog-academy/media/ and press
# Academy → Import Videos → Find new videos. The key is then moved to a
# private folder automatically.
#
# Needs ffmpeg (free): macOS `brew install ffmpeg`, Ubuntu `sudo apt install ffmpeg`,
# Windows: use encode-lesson.ps1 instead.

set -euo pipefail

AUDIO_ONLY=0
NAME=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    --audio) AUDIO_ONLY=1; shift ;;
    --name) NAME="$2"; shift 2 ;;
    -h|--help) sed -n '2,20p' "$0"; exit 0 ;;
    *) INPUT="$1"; shift ;;
  esac
done

if [[ -z "${INPUT:-}" || ! -f "$INPUT" ]]; then
  echo "Give the path to a video or audio file. Example: ./encode-lesson.sh lesson.mp4" >&2
  exit 1
fi
command -v ffmpeg >/dev/null || { echo "ffmpeg is not installed." >&2; exit 1; }
command -v openssl >/dev/null || { echo "openssl is not installed." >&2; exit 1; }

if [[ -z "$NAME" ]]; then
  base="$(basename "$INPUT")"
  base="${base%.*}"
  NAME="$(echo "$base" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+|-+$//g')"
fi
[[ "$NAME" =~ ^[a-z0-9_-]+$ ]] || { echo "Folder name may only use a-z, 0-9, - and _." >&2; exit 1; }

OUT="$NAME"
if [[ -e "$OUT" ]]; then
  echo "A folder called '$OUT' already exists. Delete it or use --name." >&2
  exit 1
fi
mkdir -p "$OUT"

# A fresh random 128-bit key for this lesson.
openssl rand 16 > "$OUT/enc.key"
KEYINFO="$(mktemp)"
trap 'rm -f "$KEYINFO"' EXIT
printf 'key://lesson\n%s\n%s\n' "$OUT/enc.key" "$(openssl rand -hex 16)" > "$KEYINFO"

COMMON=(-hls_time 6 -hls_playlist_type vod -hls_flags single_file -hls_key_info_file "$KEYINFO" -master_pl_name master.m3u8)

if [[ $AUDIO_ONLY -eq 1 ]]; then
  ffmpeg -hide_banner -loglevel warning -stats -i "$INPUT" -vn \
    -map 0:a:0 -c:a aac -b:a 96k -ac 2 \
    -f hls "${COMMON[@]}" -var_stream_map "a:0,name:audio" \
    -hls_segment_filename "$OUT/%v/media.ts" "$OUT/%v/index.m3u8"
else
  # Three qualities: 360p for slow mobile data, 540p, and 720p.
  # Key frames every 2 seconds so the player can switch quality smoothly.
  ffmpeg -hide_banner -loglevel warning -stats -i "$INPUT" \
    -filter_complex "[0:v]split=3[v1][v2][v3];[v1]scale=-2:360[v360];[v2]scale=-2:540[v540];[v3]scale=-2:720[v720]" \
    -map "[v360]" -map 0:a:0 -map "[v540]" -map 0:a:0 -map "[v720]" -map 0:a:0 \
    -c:v libx264 -preset medium -profile:v main -pix_fmt yuv420p -sc_threshold 0 \
    -force_key_frames "expr:gte(t,n_forced*2)" \
    -b:v:0 700k  -maxrate:v:0 770k  -bufsize:v:0 1400k \
    -b:v:1 1400k -maxrate:v:1 1540k -bufsize:v:1 2800k \
    -b:v:2 2500k -maxrate:v:2 2750k -bufsize:v:2 5000k \
    -c:a aac -b:a 96k -ac 2 \
    -f hls "${COMMON[@]}" \
    -var_stream_map "v:0,a:0,name:360p v:1,a:1,name:540p v:2,a:2,name:720p" \
    -hls_segment_filename "$OUT/%v/media.ts" "$OUT/%v/index.m3u8"
fi

# ffmpeg leaves temporary files behind with single-file output.
find "$OUT" -name '*.tmp' -delete

SIZE="$(du -sh "$OUT" | cut -f1)"
echo ""
echo "Done: $OUT/ ($SIZE)"
echo "Next: upload the folder '$OUT' to wp-content/uploads/nirog-academy/media/"
echo "      then open Academy → Import Videos → Find new videos."
