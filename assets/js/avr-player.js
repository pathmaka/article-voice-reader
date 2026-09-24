(function () {
	var i18n = window.avrPlayerData || {};
	var notSupportedText = i18n.notSupported || 'Voice reading is not supported in this browser.';

	if (!('speechSynthesis' in window)) {
		document.querySelectorAll('.avr-widget').forEach(function (el) {
			el.innerHTML = '<em>' + notSupportedText + '</em>';
		});
		return;
	}

	var voicesList = [];
	var voicesResolved = false;

	function loadVoices() {
		var currentList = window.speechSynthesis.getVoices();
		if (currentList.length > 0) {
			voicesList = currentList;
			voicesResolved = true;
		} else if (!voicesResolved) {
			return; // Not loaded yet — wait for onvoiceschanged or the fallback below.
		}
		document.querySelectorAll('.avr-widget').forEach(populateVoiceSelect);
	}

	function populateVoiceSelect(widget) {
		if (!voicesResolved) {
			return; // Avoid deciding to remove the dropdown before we truly know the count.
		}
		var label = widget.querySelector('.avr-voice-label');
		if (!label) {
			return;
		}
		if (voicesList.length <= 1) {
			label.remove();
			return;
		}
		var select = label.querySelector('.avr-voice-select');
		if (!select || select.dataset.populated) {
			return;
		}
		voicesList.forEach(function (voice, i) {
			var opt = document.createElement('option');
			opt.value = i;
			opt.textContent = voice.name + ' (' + voice.lang + ')';
			select.appendChild(opt);
		});
		select.dataset.populated = '1';
	}

	window.speechSynthesis.onvoiceschanged = loadVoices;
	loadVoices();

	// Fallback for browsers that never fire onvoiceschanged: give it one second,
	// then accept whatever getVoices() reports (even if that's still empty/one).
	setTimeout(function () {
		if (!voicesResolved) {
			voicesResolved = true;
			voicesList = window.speechSynthesis.getVoices();
			document.querySelectorAll('.avr-widget').forEach(populateVoiceSelect);
		}
	}, 1000);

	// Rough, deliberately conservative fallback for estimating reading position
	// from elapsed time when the browser/voice never fires "boundary" events —
	// biased slow so the fallback re-reads a word or two rather than skipping
	// ahead of what was actually spoken. ~150 wpm at 1x, in characters/second.
	var FALLBACK_CHARS_PER_SECOND = 12;

	document.querySelectorAll('.avr-widget').forEach(function (widget) {
		var fullText = JSON.parse(widget.dataset.text || '""');
		var remainingText = fullText;
		var lastCharIndex = 0;
		var utteranceStartedAt = 0;
		var playState = 'idle'; // idle | playing | paused — tracked ourselves rather
		// than trusting speechSynthesis.speaking/.paused, which can desync from
		// reality in some browsers (notably Chrome) after a few cancel()/speak()
		// cycles, silently making every decision built on them wrong.
		var utteranceId = 0;
		var playBtn = widget.querySelector('.avr-play-btn');
		var toggleBtn = widget.querySelector('.avr-toggle-btn');
		var closeBtn = widget.querySelector('.avr-close-btn');
		var speedSlider = widget.querySelector('.avr-speed-slider');
		var speedValueDisplay = widget.querySelector('.avr-speed-value');
		var status = widget.querySelector('.avr-status');

		function getVoiceSelect() {
			return widget.querySelector('.avr-voice-select');
		}

		function setIcon(playing) {
			playBtn.innerHTML = playing ? '&#10074;&#10074;' : '&#9654;';
			playBtn.setAttribute('aria-label', playing ? 'Pause' : 'Play');
		}

		function makeUtterance(textToSpeak) {
			var id = ++utteranceId;
			var u = new SpeechSynthesisUtterance(textToSpeak);
			u.rate = parseFloat(speedSlider.value) || 1;
			var voiceSelect = getVoiceSelect();
			if (voiceSelect && voiceSelect.value !== '' && voicesList[voiceSelect.value]) {
				u.voice = voicesList[voiceSelect.value];
			}
			u.onboundary = function (e) {
				if (id !== utteranceId) {
					return; // Stale utterance we've already moved on from.
				}
				lastCharIndex = e.charIndex;
			};
			u.onend = function () {
				if (id !== utteranceId) {
					return;
				}
				playState = 'idle';
				setIcon(false);
				status.textContent = 'Finished';
				remainingText = fullText;
				lastCharIndex = 0;
			};
			u.onerror = function (e) {
				if (id !== utteranceId) {
					return;
				}
				if (e.error === 'canceled' || e.error === 'interrupted') {
					return; // Expected — triggered by our own cancel()/speak() restarts.
				}
				status.textContent = 'There was a problem playing the audio.';
			};
			return u;
		}

		// Speaks `text` from its start, replacing whatever is currently queued.
		// Every state change — Play, Resume, and restarts triggered by speed or
		// voice changes — routes through this single function, so there's only
		// one place that ever talks to speechSynthesis.speak()/cancel(). Native
		// pause()/resume() are intentionally never used: some browsers (notably
		// Chrome) get the speech engine stuck if cancel() is called on a paused
		// utterance, or silently fail to actually resume audio — so "pause" and
		// "resume" below are implemented as cancel + remember/restart position
		// instead of relying on the browser's own pause/resume pair.
		function speakFrom(text) {
			window.speechSynthesis.cancel();

			if (text.trim() === '') {
				playState = 'idle';
				setIcon(false);
				status.textContent = 'Finished';
				remainingText = fullText;
				lastCharIndex = 0;
				return;
			}

			remainingText = text;
			lastCharIndex = 0;
			utteranceStartedAt = Date.now();
			window.speechSynthesis.speak(makeUtterance(text));
			playState = 'playing';
			setIcon(true);
			status.textContent = 'Playing…';
		}

		// Best-known position reached in the current utterance: real "boundary"
		// tracking where the browser/voice provides it, otherwise a time-based
		// estimate. Some voices (particularly after switching voice mid-read)
		// never fire boundary events at all, which would otherwise leave
		// lastCharIndex stuck at 0 and make pausing/resuming jump all the way
		// back to wherever that utterance started.
		function currentPositionIndex() {
			var rate = parseFloat(speedSlider.value) || 1;
			var elapsedSeconds = (Date.now() - utteranceStartedAt) / 1000;
			var estimate = Math.floor(elapsedSeconds * FALLBACK_CHARS_PER_SECOND * rate);
			return Math.max(lastCharIndex, Math.min(estimate, remainingText.length));
		}

		function pauseSpeaking() {
			window.speechSynthesis.cancel();
			remainingText = remainingText.slice(currentPositionIndex());
			lastCharIndex = 0;
			playState = 'paused';
			setIcon(false);
			status.textContent = 'Paused';
		}

		// Restarts from roughly where playback left off (rather than from the
		// top) when the voice or speed changes mid-read, or when resuming from
		// pause. See currentPositionIndex() for how that position is tracked.
		function restartFromCurrentPosition() {
			speakFrom(remainingText.slice(currentPositionIndex()));
		}

		playBtn.addEventListener('click', function () {
			if (playState === 'playing') {
				pauseSpeaking();
				return;
			}
			if (playState === 'paused') {
				speakFrom(remainingText);
				return;
			}
			speakFrom(fullText);
		});

		function formatSpeed(value) {
			var num = parseFloat(value);
			return (Math.round(num * 100) / 100).toString().replace(/\.?0+$/, '') + 'x';
		}

		speedSlider.addEventListener('input', function () {
			speedValueDisplay.textContent = formatSpeed(speedSlider.value);
		});

		speedSlider.addEventListener('change', function () {
			if (playState === 'playing') {
				restartFromCurrentPosition();
			}
		});

		widget.addEventListener('change', function (e) {
			if (e.target.classList.contains('avr-voice-select') && playState === 'playing') {
				restartFromCurrentPosition();
			}
		});

		toggleBtn.addEventListener('click', function () {
			widget.classList.add('avr-open');
		});

		closeBtn.addEventListener('click', function () {
			widget.classList.remove('avr-open');
		});
	});

	window.addEventListener('beforeunload', function () {
		window.speechSynthesis.cancel();
	});
})();
