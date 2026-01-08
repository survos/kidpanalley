import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        url: String,
        content: String,  // inline ChordPro content
        format: String,   // auto-detect if not provided
        mode: { type: String, default: 'full' },
        width: { type: Number, default: 800 },
        height: { type: Number, default: 600 }
    }

    connect() {
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

        let html

        switch (this.modeValue) {
            case 'lyrics':
                html = this.renderLyricsOnly()
                break
            case 'chords':
            case 'full':
            default:
                const formatter = new this.ChordSheetJS.HtmlDivFormatter()
                html = this.renderMetadata() + formatter.format(this.song)
        }

        this.element.innerHTML = `
            ${this.renderModeButtons()}
            <div class="chord-sheet ${this.modeValue}-mode">${html}</div>
        `
    }

    renderLyricsOnly() {
        const lines = []
        for (const line of this.song.lines) {
            const lyrics = line.items
                .filter(item => item.lyrics)
                .map(item => item.lyrics)
                .join('')
            if (lyrics.trim()) lines.push(lyrics)
        }
        return `<div class="lyrics-only">${lines.map(l => `<p>${l}</p>`).join('')}</div>`
    }

    renderMetadata() {
        const meta = this.song.metadata
        const parts = []
        if (meta.title) parts.push(`<h2 class="song-title">${meta.title}</h2>`)
        if (meta.artist) parts.push(`<p class="song-artist">${meta.artist}</p>`)
        return parts.join('')
    }

    renderModeButtons() {
        return `
            <div class="btn-group mb-3" role="group">
                <button type="button"
                        class="btn btn-sm ${this.modeValue === 'lyrics' ? 'btn-primary' : 'btn-outline-primary'}"
                        data-action="click->music-display#setMode"
                        data-mode="lyrics">Lyrics</button>
                <button type="button"
                        class="btn btn-sm ${this.modeValue === 'chords' ? 'btn-primary' : 'btn-outline-primary'}"
                        data-action="click->music-display#setMode"
                        data-mode="chords">Chords</button>
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
