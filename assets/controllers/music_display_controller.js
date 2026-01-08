import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        url: String,
        content: String,
        format: String,
        mode: { type: String, default: 'full' },
        width: { type: Number, default: 800 },
        height: { type: Number, default: 600 }
    }

    connect() {
        this.injectStyles()
        this.loadMusic()
    }

    get detectedFormat() {
        if (this.formatValue) return this.formatValue
        if (this.contentValue) return 'chordpro'

        const ext = this.urlValue?.split('.').pop()?.toLowerCase()
        if (['xml', 'mxl', 'musicxml'].includes(ext)) return 'musicxml'
        if (['cho', 'chordpro', 'pro', 'chopro', 'txt'].includes(ext)) return 'chordpro'

        return 'unknown'
    }

    injectStyles() {
        if (document.getElementById('chordpro-styles')) return

        const style = document.createElement('style')
        style.id = 'chordpro-styles'
        style.textContent = `
            .chord-sheet {
                font-family: 'Georgia', serif;
                line-height: 1.8;
                max-width: 800px;
            }

            .chord-sheet .song {
                display: flex;
                flex-direction: column;
                gap: 1.5em;
            }

            /* Metadata header */
            .chord-sheet .song-title {
                font-size: 1.5em;
                font-weight: bold;
                margin-bottom: 0.25em;
            }
            .chord-sheet .song-artist {
                font-style: italic;
                color: #666;
                margin-bottom: 1em;
            }

            /* Base paragraph/section styling */
            .chord-sheet .paragraph {
                margin-bottom: 1em;
            }

            /* Verse: left-aligned, full width */
            .chord-sheet .verse,
            .chord-sheet .paragraph:not(.chorus):not(.bridge):not(.tab) {
                margin-left: 0;
                margin-right: auto;
                max-width: 70%;
            }

            /* Chorus/Refrain: indented right */
            .chord-sheet .chorus {
                margin-left: auto;
                margin-right: 0;
                max-width: 70%;
                padding-left: 2em;
                border-left: 3px solid #4a90a4;
                background: linear-gradient(to right, rgba(74, 144, 164, 0.05), transparent);
            }

            /* Bridge: centered, visually distinct */
            .chord-sheet .bridge {
                margin-left: auto;
                margin-right: auto;
                max-width: 60%;
                text-align: center;
                font-style: italic;
                padding: 1em 2em;
                border-top: 1px dashed #999;
                border-bottom: 1px dashed #999;
                background: rgba(0,0,0,0.02);
            }

            /* Section labels */
            .chord-sheet .chorus::before {
                content: "Chorus";
                display: block;
                font-size: 0.75em;
                font-weight: bold;
                color: #4a90a4;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin-bottom: 0.5em;
            }

            .chord-sheet .bridge::before {
                content: "Bridge";
                display: block;
                font-size: 0.75em;
                font-weight: bold;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin-bottom: 0.5em;
            }

            /* Chord and lyric alignment */
            .chord-sheet .row {
                display: flex;
                flex-wrap: wrap;
                white-space: pre-wrap;
            }

            .chord-sheet .column {
                display: inline-flex;
                flex-direction: column;
            }

            .chord-sheet .chord {
                color: #c44;
                font-weight: bold;
                font-family: 'Monaco', 'Consolas', monospace;
                font-size: 0.85em;
                min-height: 1.2em;
            }

            .chord-sheet .lyrics {
                white-space: pre-wrap;
            }

            /* Comment styling */
            .chord-sheet .comment {
                color: #666;
                font-style: italic;
                font-size: 0.9em;
            }

            /* Lyrics-only mode */
            .chord-sheet.lyrics-mode .chord {
                display: none;
            }

            .chord-sheet.lyrics-mode .row {
                display: block;
            }

            .chord-sheet.lyrics-mode .column {
                display: inline;
            }

            /* Print styles */
            @media print {
                .chord-sheet .chorus {
                    border-left: 2px solid #000;
                    background: none;
                }
                .chord-sheet .bridge {
                    border-color: #000;
                }
                .chord-sheet .chord {
                    color: #000;
                }
            }
        `
        document.head.appendChild(style)
    }

    async loadMusic() {
        if (!this.urlValue && !this.contentValue) {
            this.showError('No music URL or content provided')
            return
        }

        try {
            const format = this.detectedFormat

            if (format === 'musicxml') {
                await this.loadMusicXML()
            } else if (format === 'chordpro') {
                await this.loadChordPro()
            } else {
                this.showError(`Unknown format for: ${this.urlValue}`)
            }
        } catch (error) {
            console.error('Error loading music:', error)
            this.showError(error.message)
        }
    }

    async loadMusicXML() {
        const osmdModule = await import("opensheetmusicdisplay")
        const OpenSheetMusicDisplay = osmdModule.default.OpenSheetMusicDisplay

        this.osmd = new OpenSheetMusicDisplay(this.element, {
            autoResize: true,
            backend: "svg",
            drawTitle: true,
            drawSubtitle: true,
            drawComposer: true,
            drawCredits: true,
            drawPartNames: true,
            coloringEnabled: true,
            defaultColorNotehead: "#CC0055",
            defaultColorStem: "#CC0055"
        })

        await this.osmd.load(this.urlValue)
        this.osmd.render()
    }

    async loadChordPro() {
        let text = this.contentValue

        if (!text && this.urlValue) {
            const response = await fetch(this.urlValue)
            text = await response.text()
        }

        const ChordSheetJS = await import("chordsheetjs")
        const parser = new ChordSheetJS.ChordProParser()
        this.song = parser.parse(text)
        this.ChordSheetJS = ChordSheetJS

        this.renderChordPro()
    }

    renderChordPro() {
        if (!this.song) return

        const formatter = new this.ChordSheetJS.HtmlDivFormatter()
        let html = formatter.format(this.song)

        // Add section classes that ChordSheetJS might miss
        html = this.enhanceSectionMarkup(html)

        this.element.innerHTML = `
            ${this.renderModeButtons()}
            <div class="chord-sheet ${this.modeValue}-mode">
                ${this.renderMetadata()}
                <div class="song">${html}</div>
            </div>
        `
    }

    enhanceSectionMarkup(html) {
        // ChordSheetJS uses data attributes, convert to classes for easier styling
        return html
            .replace(/class="paragraph"\s*data-type="chorus"/g, 'class="paragraph chorus"')
            .replace(/class="paragraph"\s*data-type="bridge"/g, 'class="paragraph bridge"')
            .replace(/class="paragraph"\s*data-type="verse"/g, 'class="paragraph verse"')
    }

    renderMetadata() {
        const meta = this.song.metadata
        const parts = []

        if (meta.title) {
            parts.push(`<div class="song-title">${meta.title}</div>`)
        }
        if (meta.artist || meta.subtitle) {
            parts.push(`<div class="song-artist">${meta.artist || meta.subtitle}</div>`)
        }

        return parts.join('')
    }

    renderModeButtons() {
        return `
            <div class="btn-group mb-3" role="group">
                <button type="button"
                        class="btn btn-sm ${this.modeValue === 'lyrics' ? 'btn-primary' : 'btn-outline-primary'}"
                        data-action="click->music-display#setMode"
                        data-mode="lyrics">Lyrics Only</button>
                <button type="button"
                        class="btn btn-sm ${this.modeValue === 'full' ? 'btn-primary' : 'btn-outline-primary'}"
                        data-action="click->music-display#setMode"
                        data-mode="full">With Chords</button>
            </div>
        `
    }

    setMode(event) {
        this.modeValue = event.target.dataset.mode
        this.renderChordPro()
    }

    showError(message) {
        this.element.innerHTML = `<div class="alert alert-danger">Error: ${message}</div>`
    }

    disconnect() {
        if (this.osmd) this.osmd.clear()
    }
}
