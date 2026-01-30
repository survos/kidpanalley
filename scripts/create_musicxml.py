#!/usr/bin/env python3
import argparse
import json
import sys
from pathlib import Path

from music21 import chord, clef, converter, harmony, metadata, note


def parse_timestamp(value: str) -> float:
    if not value:
        return 0.0
    try:
        hours, minutes, rest = value.split(":")
        seconds, millis = rest.split(",")
        return (
            int(hours) * 3600
            + int(minutes) * 60
            + int(seconds)
            + (int(millis) / 1000.0)
        )
    except ValueError:
        return 0.0


def load_whisper_segments(path: Path):
    data = json.loads(path.read_text())
    segments = data.get("segments")
    if not segments and isinstance(data.get("transcription"), list):
        segments = data.get("transcription", [])

    result = []
    for segment in segments or []:
        text = (segment.get("text") or "").strip()
        if not text:
            continue

        if "start" in segment or "end" in segment:
            start = float(segment.get("start", 0.0))
            end = float(segment.get("end", 0.0))
        else:
            offsets = segment.get("offsets") or {}
            timestamps = segment.get("timestamps") or {}
            start = float(offsets.get("from", 0.0)) / 1000.0
            end = float(offsets.get("to", 0.0)) / 1000.0
            if not end and timestamps:
                start = parse_timestamp(timestamps.get("from", ""))
                end = parse_timestamp(timestamps.get("to", ""))

        result.append({"start": start, "end": end, "text": text})
    return result


def normalize_words(text: str):
    return [word for word in text.replace("\n", " ").split(" ") if word]


def assign_lyrics_by_time(score, segments):
    if not segments:
        return

    secmap = score.secondsMap
    note_entries = [
        entry
        for entry in secmap
        if isinstance(entry.get("element"), (note.Note, chord.Chord))
    ]

    word_queue = []
    seg_index = 0

    for entry in note_entries:
        note_obj = entry["element"]
        start = entry.get("offsetSeconds", 0.0)
        end = start + entry.get("durationSeconds", 0.0)

        while seg_index < len(segments) and segments[seg_index]["end"] <= start:
            seg_index += 1

        while seg_index < len(segments) and not word_queue:
            segment = segments[seg_index]
            if segment["start"] <= end:
                word_queue.extend(normalize_words(segment["text"]))
            if segment["end"] <= end:
                seg_index += 1
            else:
                break

        if word_queue:
            note_obj.lyric = word_queue.pop(0)


def assign_lyrics_sequential(score, segments):
    words = []
    for segment in segments:
        words.extend(normalize_words(segment["text"]))
    if not words:
        return

    word_index = 0
    for element in score.recurse().notes:
        if word_index >= len(words):
            break
        element.lyric = words[word_index]
        word_index += 1


def add_chord_symbols(score):
    chords = score.chordify().flatten().getElementsByClass(chord.Chord)
    if not chords:
        return
    if hasattr(score, "parts") and score.parts:
        part = score.parts[0]
    else:
        part = score
    for chord_obj in chords:
        try:
            symbol = harmony.chordSymbolFromChord(chord_obj)
        except Exception:
            continue
        part.insert(chord_obj.offset, symbol)


def force_treble_clef(score):
    parts = list(score.parts) if hasattr(score, "parts") else [score]
    for part in parts:
        for measure in part.getElementsByClass("Measure"):
            if measure.getElementsByClass("Clef"):
                continue
            measure.insert(0, clef.TrebleClef())
        break


def reduce_to_melody(score):
    melody = score.chordify()
    for chord_obj in melody.recurse().getElementsByClass(chord.Chord):
        if not chord_obj.pitches:
            continue
        pitch = max(chord_obj.pitches)
        replacement = note.Note(pitch)
        replacement.duration = chord_obj.duration
        parent = chord_obj.activeSite
        if parent is not None:
            parent.replace(chord_obj, replacement)
    return melody


def simplify_score(score, grid: float, melody_only: bool):
    if grid <= 0:
        return

    if melody_only:
        score = reduce_to_melody(score)
    else:
        score = score.chordify()
    for element in score.recurse().notesAndRests:
        duration = element.duration
        if duration is None:
            continue
        ql = float(duration.quarterLength)
        if ql <= 0:
            continue
        if melody_only:
            duration.quarterLength = 1.0
        else:
            snapped = max(grid, round(ql / grid) * grid)
            duration.quarterLength = snapped
        duration.tuplets = []

    score.makeMeasures(inPlace=True)
    score.stripTies(inPlace=True)
    return score


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--midi", required=True)
    parser.add_argument("--whisper-json", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--title")
    parser.add_argument("--creator")
    parser.add_argument("--subtitle")
    parser.add_argument("--simplify", action="store_true")
    parser.add_argument("--grid", type=float, default=0.5)
    parser.add_argument("--melody", action="store_true")
    args = parser.parse_args()

    midi_path = Path(args.midi)
    whisper_path = Path(args.whisper_json)
    output_path = Path(args.output)

    score = converter.parse(midi_path.as_posix())

    def normalize_metadata_value(value: str | None) -> str | None:
        if value is None:
            return None
        cleaned = value.strip()
        if not cleaned:
            return None
        return cleaned

    title = normalize_metadata_value(args.title)
    creator = normalize_metadata_value(args.creator)
    subtitle = normalize_metadata_value(args.subtitle)

    if title or creator or subtitle:
        if score.metadata is None:
            score.metadata = metadata.Metadata()
    if title:
        score.metadata.title = title
        score.metadata.movementName = title
    if creator:
        score.metadata.composer = creator
    if subtitle:
        score.metadata.subtitle = subtitle
    segments = load_whisper_segments(whisper_path)
    if args.simplify:
        simplified = simplify_score(score, args.grid, args.melody)
        if simplified is not None:
            score = simplified
        assign_lyrics_sequential(score, segments)
    else:
        assign_lyrics_by_time(score, segments)
        assign_lyrics_sequential(score, segments)
    force_treble_clef(score)
    add_chord_symbols(score)

    lyric_count = sum(1 for element in score.recurse().notes if element.lyric)
    if lyric_count == 0:
        print(
            f"No lyrics applied. segments={len(segments)} notes={len(list(score.recurse().notes))}",
            file=sys.stderr,
        )
    score.write("musicxml", fp=output_path.as_posix())


if __name__ == "__main__":
    main()
