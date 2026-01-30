#!/usr/bin/env bash
set -euo pipefail

sudo apt update
sudo apt install -y python3.11 python3.11-venv python3.11-dev ffmpeg

VENV_DIR="${HOME}/.venvs/lyric-music"
python3.11 -m venv "$VENV_DIR"
source "$VENV_DIR/bin/activate"

pip install -U pip
pip install -U basic-pitch music21 librosa soundfile mido

echo "Installed lyric tools in $VENV_DIR"
echo "basic-pitch: $VENV_DIR/bin/basic-pitch"
echo "python: $VENV_DIR/bin/python"
