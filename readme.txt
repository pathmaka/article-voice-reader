=== Article Voice Reader ===
Contributors: pathmaka
Tags: text to speech, accessibility, audio, speech synthesis, read aloud
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

A floating "Listen to this article" widget powered by the visitor's own browser. No API keys, no external services, no ongoing cost.

== Description ==

Article Voice Reader adds a floating text-to-speech widget to your posts and pages, using the browser's built-in [Web Speech API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API) (`SpeechSynthesisUtterance`). Nothing is sent to any external server — the entire reading happens locally in the visitor's browser.

= Features =

* **Floating widget** — collapses to a small icon; click to expand into the full player
* **Play / Pause** toggle, with a **Speed slider** (0.5x–2x)
* **Voice selector** — automatically shown only when the visitor's browser actually offers more than one voice; hidden entirely otherwise
* **Six position presets** — Top Left, Top Right, Middle Left, Middle Right, Bottom Left, Bottom Right
* **Customizable icon color** via a native WordPress color picker
* **Customizable widget title**, also shown as a hover tooltip on the collapsed icon
* **`[voice_reader]` shortcode** for manual placement, if you'd rather not auto-insert it everywhere
* **Reset to Defaults** button to wipe customizations and start over
* Fully self-contained icon (inline SVG) — no external requests, not even to WordPress's own emoji CDN

= Notes & Limitations =

* Voice availability is entirely browser/OS-dependent. Desktop Chrome tends to offer several voices; some mobile browsers offer only one (or none) — in the latter case, the Voice dropdown is automatically hidden.
* Changing speed or voice mid-playback resumes from roughly the last spoken word (tracked via the browser's `boundary` events) rather than restarting from the top — but this depends on the browser/voice actually firing those events. When it isn't supported, it falls back to restarting from the beginning.
* Closing the widget (×) does not pause or stop playback — it keeps reading in the background, the same way a minimized media player would. Reopening the panel reflects the correct Play/Pause state.
* Some browsers' built-in voices (e.g. certain Chrome "network" voices) stream over the internet in the background as an inherent part of how the browser implements them — this is outside the plugin's control either way.

== Installation ==

= From your WordPress dashboard =

1. Visit **Plugins → Add New**
2. Search for "Article Voice Reader"
3. Click **Install Now**, then **Activate**

= Manual installation =

1. Download the plugin as a `.zip` file
2. Upload the `article-voice-reader` folder to `/wp-content/plugins/`, or install it via **Plugins → Add New → Upload Plugin**
3. Activate **Article Voice Reader** from the Plugins screen

= Once activated =

Configure it at **Settings → Article Voice Reader**.

= Settings =

* **Enable on** — Choose whether the widget appears on Posts, Pages, or both
* **Widget** — Toggle automatic insertion on/off. If disabled, place it manually with the `[voice_reader]` shortcode — it still floats at the position chosen below regardless of where the shortcode is placed
* **Position** — Where the widget floats: Top/Middle/Bottom × Left/Right (6 options)
* **Icon color** — Background color of the icon and Play button, via a standard color picker
* **Default speed** — Initial playback speed (0.5x–2x) before a visitor adjusts the slider
* **Widget title** — The text shown inside the expanded panel and as the hover tooltip on the collapsed icon
* **Reset to Defaults** — Wipes all of the above back to their original defaults (asks for confirmation first)

== Frequently Asked Questions ==

= Does this send my content to any external service? =

No. The plugin extracts the plain text of a post/page server-side and hands it to the visitor's own browser via the Web Speech API. Nothing is sent to any external server — the entire reading happens locally in the visitor's browser.

= Why don't I see a Voice dropdown? =

The dropdown is only shown when the visitor's browser reports more than one available voice. Voice availability is entirely browser/OS-dependent — some mobile browsers only expose one voice (or none).

= Can I place the widget manually instead of having it auto-inserted? =

Yes. Turn off automatic insertion under Settings → Article Voice Reader → Widget, then add the `[voice_reader]` shortcode to any post or page where you want it. Note that it always floats at the position configured in settings, regardless of where the shortcode itself is placed in the content.

= Does this work on mobile browsers? =

It works wherever the Web Speech API is supported, which varies by browser and OS. Voice quality and the number of available voices depend entirely on the visitor's browser and device.

== Screenshots ==

1. Collapsed widget — the floating icon as visitors first see it
2. Expanded widget — the player panel with Play/Pause, speed slider, and voice selector
3. Settings page — Settings → Article Voice Reader in wp-admin
4. Widget while actively reading an article aloud

== Changelog ==

= 1.0.0 =
* Initial release
* Floating widget (collapsible icon → expands to full player)
* Play/Pause toggle with adjustable speed (0.5x–2x slider)
* Optional voice selector, shown only when the browser offers more than one voice
* Six position presets, customizable icon color, and customizable widget title
* `[voice_reader]` shortcode for manual placement
* Reset to Defaults option
* Self-contained inline SVG icon (no external requests)

== Upgrade Notice ==

= 1.0.0 =
Initial release.
