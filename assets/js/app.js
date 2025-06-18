document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('recordingForm');
    const recordButton = document.getElementById('recordButton');
    const stopButton = document.getElementById('stopButton');
    const recordingSection = document.getElementById('recordingSection');
    const uploadSection = document.getElementById('uploadSection');
    const textSection = document.getElementById('textSection');
    const textContent = document.getElementById('textContent');
    const contactPhone = document.getElementById('contactPhone');
    const audioFile = document.getElementById('audioFile');
    let mediaRecorder;
    let audioChunks = [];
    let hasRecording = false;
    let recordingStartTime;
    let recordingDuration = 0;
    let recordingTimer;

    // Validate that all required elements exist
    if (!form || !recordButton || !stopButton || !recordingSection || 
        !uploadSection || !textSection || !textContent || !contactPhone || !audioFile) {
        console.error('Required form elements not found');
        return;
    }

    // Function to set cookie
    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }

    // Function to get cookie
    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for(let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    // Initialize language from cookie or default to English
    const savedLang = getCookie('user_language') || 'en';
    setLanguage(savedLang);
    
    // Update active button state
    document.querySelectorAll('.language-switcher button').forEach(button => {
        button.classList.remove('active');
        if (button.dataset.lang === savedLang) {
            button.classList.add('active');
        }
    });

    // Add click event listeners to language switcher buttons
    document.querySelectorAll('.language-switcher button').forEach(button => {
        button.addEventListener('click', function() {
            const lang = this.dataset.lang;
            setLanguage(lang);
            setCookie('user_language', lang, 365); // Store for 1 year
            
            // Update active button state
            document.querySelectorAll('.language-switcher button').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
        });
    });

    // Check if browser supports audio recording
    function isRecordingSupported() {
        return !!(navigator.mediaDevices && 
                 navigator.mediaDevices.getUserMedia && 
                 window.MediaRecorder);
    }

    // Function to request microphone permissions
    async function requestMicrophonePermission() {
        if (!isRecordingSupported()) {
            showNotification(t('recordingNotSupported'), 'error');
            return false;
        }

        try {
            // First check if we're in a secure context
            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
                showNotification(t('httpsRequired'), 'error');
                return false;
            }

            // Request permission explicitly
            const stream = await navigator.mediaDevices.getUserMedia({ 
                audio: true
            });

            // If we get here, permission was granted
            stream.getTracks().forEach(track => track.stop());
            return true;
        } catch (err) {
            console.error('Error requesting microphone permission:', err);
            
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                showNotification(t('allowMicrophone'), 'error');
            } else if (err.name === 'NotFoundError') {
                showNotification(t('noMicrophone'), 'error');
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                showNotification(t('microphoneBusy'), 'error');
            } else if (err.name === 'SecurityError') {
                showNotification(t('httpsRequired'), 'error');
            } else {
                showNotification(t('microphoneError'), 'error');
            }
            return false;
        }
    }

    // Phone number validation
    contactPhone.addEventListener('keydown', function(e) {
        // Always allow tab key for navigation
        if (e.key === 'Tab' || e.keyCode === 9) {
            return; // Allow default tab behavior
        }
        
        const allowedKeys = ['+', 'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
        if (!allowedKeys.includes(e.key) && !/^[0-9]$/.test(e.key)) {
            e.preventDefault();
        }
    });

    contactPhone.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9+]/g, '');
    });

    // Function to show notification
    function showNotification(message, type = 'info') {
        const notificationContainer = document.getElementById('notificationContainer');
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'error' : 'info'} alert-dismissible fade show`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        notificationContainer.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    // Function to format time in MM:SS format
    function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    // Function to update recording duration display
    function updateRecordingDuration() {
        const durationElement = document.getElementById('recordingDuration');
        if (durationElement) {
            recordingDuration = (Date.now() - recordingStartTime) / 1000;
            durationElement.textContent = formatTime(recordingDuration);
        }
    }

    // Function to create audio player
    function createAudioPlayer(audioBlob) {
        const audioUrl = URL.createObjectURL(audioBlob);
        const audioPlayer = document.createElement('div');
        audioPlayer.className = 'audio-player mt-3';
        
        // Only show duration if it's greater than 0
        const durationHtml = recordingDuration > 0 ? 
            `<div class="form-text mt-1"><span data-i18n="recordingDuration">Recording duration</span>: <span id="recordingDuration">${formatTime(recordingDuration)}</span></div>` : '';
        
        audioPlayer.innerHTML = `
            <div class="d-flex align-items-center gap-3">
                <audio controls class="form-control">
                    <source src="${audioUrl}" type="${audioBlob.type}">
                    Your browser does not support the audio element.
                </audio>
                <button type="button" class="btn btn-danger" id="deleteRecording">
                    <i class="fas fa-trash"></i> <span data-i18n="delete">Delete</span>
                </button>
            </div>
            ${durationHtml}
        `;
        
        // Add delete recording functionality
        audioPlayer.querySelector('#deleteRecording').addEventListener('click', () => {
            audioPlayer.remove();
            hasRecording = false;
            audioChunks = [];
            recordingDuration = 0;
            const durationElement = document.getElementById('recordingDuration');
            if (durationElement) {
                durationElement.textContent = '';
            }
        });

        return audioPlayer;
    }

    // Function to convert audio data to WAV
    function audioBufferToWav(buffer) {
        const numOfChannels = buffer.numberOfChannels;
        const sampleRate = buffer.sampleRate;
        const format = 1; // PCM
        const bitDepth = 16;
        const bytesPerSample = bitDepth / 8;
        const blockAlign = numOfChannels * bytesPerSample;
        const byteRate = sampleRate * blockAlign;
        const dataSize = buffer.length * blockAlign;
        const headerSize = 44;
        const totalSize = headerSize + dataSize;
        const arrayBuffer = new ArrayBuffer(totalSize);
        const view = new DataView(arrayBuffer);

        // RIFF identifier
        writeString(view, 0, 'RIFF');
        // RIFF chunk length
        view.setUint32(4, totalSize - 8, true);
        // RIFF type
        writeString(view, 8, 'WAVE');
        // format chunk identifier
        writeString(view, 12, 'fmt ');
        // format chunk length
        view.setUint32(16, 16, true);
        // sample format (raw)
        view.setUint16(20, format, true);
        // channel count
        view.setUint16(22, numOfChannels, true);
        // sample rate
        view.setUint32(24, sampleRate, true);
        // byte rate (sample rate * block align)
        view.setUint32(28, byteRate, true);
        // block align (channel count * bytes per sample)
        view.setUint16(32, blockAlign, true);
        // bits per sample
        view.setUint16(34, bitDepth, true);
        // data chunk identifier
        writeString(view, 36, 'data');
        // data chunk length
        view.setUint32(40, dataSize, true);

        // Write the PCM samples
        const offset = 44;
        const channelData = [];
        for (let i = 0; i < numOfChannels; i++) {
            channelData.push(buffer.getChannelData(i));
        }

        let pos = 0;
        while (pos < buffer.length) {
            for (let i = 0; i < numOfChannels; i++) {
                const sample = Math.max(-1, Math.min(1, channelData[i][pos]));
                const value = sample < 0 ? sample * 0x8000 : sample * 0x7FFF;
                view.setInt16(offset + pos * blockAlign + i * bytesPerSample, value, true);
            }
            pos++;
        }

        return new Blob([arrayBuffer], { type: 'audio/wav' });
    }

    function writeString(view, offset, string) {
        for (let i = 0; i < string.length; i++) {
            view.setUint8(offset + i, string.charCodeAt(i));
        }
    }

    // Handle form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Validate form
        if (!form.checkValidity()) {
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }

        // Check input method specific validations
        const selectedMethod = document.querySelector('input[name="inputMethod"]:checked').value;
        const audioFile = document.getElementById('audioFile');
        
        switch(selectedMethod) {
            case 'record':
                if (!hasRecording) {
                    showNotification(t('recordingRequired'), 'error');
                    return;
                }
                // Verify the audio file exists in the form
                const recordedFile = form.querySelector('input[name="audioFile"]');
                if (!recordedFile || !recordedFile.files.length) {
                    showNotification(t('recordingError'), 'error');
                    return;
                }
                break;
            case 'upload':
                if (!audioFile.files.length) {
                    showNotification(t('fileRequired'), 'error');
                    return;
                }
                break;
            case 'text':
                if (!textContent.value.trim()) {
                    showNotification(t('textRequired'), 'error');
                    return;
                }
                break;
        }
        
        const severity = document.querySelector('input[name="severity"]:checked').value;
        const companyName = document.getElementById('companyName').value;

        // Add severity and company name to form data
        const severityInput = document.createElement('input');
        severityInput.type = 'hidden';
        severityInput.name = 'severity';
        severityInput.value = severity;
        form.appendChild(severityInput);

        // Add current language to form data
        const langInput = document.createElement('input');
        langInput.type = 'hidden';
        langInput.name = 'lang';
        langInput.value = document.querySelector('.language-switcher button.active').dataset.lang;
        form.appendChild(langInput);

        // Disable submit button and show loading state
        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${t('sending')}`;

        try {
            const formData = new FormData(form);
            
            const response = await fetch('process.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.success) {
                showNotification(result.message, 'info');
                form.reset();
                form.classList.remove('was-validated');
                hasRecording = false;
                
                // Clear recording elements
                const audioPlayer = document.querySelector('.audio-player');
                if (audioPlayer) {
                    audioPlayer.remove();
                }
                const durationElement = document.getElementById('recordingDuration');
                if (durationElement) {
                    durationElement.textContent = '';
                }
            } else {
                showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            showNotification(t('errorSending'), 'error');
        } finally {
            // Re-enable submit button and restore original text
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    });

    // Handle input method changes
    document.querySelectorAll('input[name="inputMethod"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove active class from all sections
            [recordingSection, uploadSection, textSection].forEach(section => {
                if (section) {
                    section.classList.remove('active');
                }
            });

            // Reset required attributes
            if (textContent) textContent.required = false;
            if (audioFile) audioFile.required = false;

            // Add active class to selected section and set required attribute
            switch(this.value) {
                case 'record':
                    if (recordingSection) recordingSection.classList.add('active');
                    break;
                case 'upload':
                    if (uploadSection) {
                        uploadSection.classList.add('active');
                        if (audioFile) audioFile.required = true;
                    }
                    break;
                case 'text':
                    if (textSection) {
                        textSection.classList.add('active');
                        if (textContent) textContent.required = true;
                    }
                    break;
            }
        });
    });

    // Start recording
    recordButton.addEventListener('click', async () => {
        // First request microphone permission
        const hasPermission = await requestMicrophonePermission();
        if (!hasPermission) {
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                audio: true
            });
            
            // Check if MediaRecorder is supported
            if (!window.MediaRecorder) {
                throw new Error('MediaRecorder not supported');
            }

            // Try to use WebM first, fall back to other formats if needed
            let mimeType = 'audio/webm;codecs=opus';
            if (!MediaRecorder.isTypeSupported(mimeType)) {
                mimeType = 'audio/wav';
                if (!MediaRecorder.isTypeSupported(mimeType)) {
                    mimeType = 'audio/mp4';
                    if (!MediaRecorder.isTypeSupported(mimeType)) {
                        mimeType = 'audio/mpeg';
                    }
                }
            }

            mediaRecorder = new MediaRecorder(stream, {
                mimeType: mimeType,
                audioBitsPerSecond: 128000
            });
            
            audioChunks = [];
            hasRecording = false;
            recordingStartTime = Date.now();
            recordingDuration = 0;

            // Start the duration timer
            recordingTimer = setInterval(updateRecordingDuration, 1000);

            mediaRecorder.addEventListener('dataavailable', event => {
                audioChunks.push(event.data);
            });

            mediaRecorder.addEventListener('stop', async () => {
                clearInterval(recordingTimer);
                const audioBlob = new Blob(audioChunks, { type: mimeType });
                
                try {
                    // Create an audio context
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    
                    // Convert blob to array buffer
                    const arrayBuffer = await audioBlob.arrayBuffer();
                    
                    // Decode the audio data
                    const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
                    
                    // Convert to WAV
                    const wavBlob = audioBufferToWav(audioBuffer);
                    
                    // Create a File object with the correct MIME type
                    const audioFile = new File([wavBlob], 'recording.wav', { 
                        type: 'audio/wav',
                        lastModified: new Date().getTime()
                    });
                    
                    // Remove any existing file input
                    const existingFileInput = form.querySelector('input[name="audioFile"]');
                    if (existingFileInput) {
                        existingFileInput.remove();
                    }
                    
                    // Create a new file input
                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.name = 'audioFile';
                    fileInput.style.display = 'none';
                    
                    // Create a new DataTransfer object and add the file
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(audioFile);
                    fileInput.files = dataTransfer.files;
                    
                    // Append the file input to the form
                    form.appendChild(fileInput);

                    // Add audio player
                    const existingPlayer = document.querySelector('.audio-player');
                    if (existingPlayer) {
                        existingPlayer.remove();
                    }
                    const audioPlayer = createAudioPlayer(wavBlob);
                    recordingSection.appendChild(audioPlayer);

                    hasRecording = true;
                } catch (error) {
                    console.error('Error processing recording:', error);
                    showNotification(t('recordingError'), 'error');
                }
            });

            // Start recording
            mediaRecorder.start();
            recordingSection.classList.add('recording');
        } catch (err) {
            console.error('Error accessing microphone:', err);
            showNotification(t('microphoneError'), 'error');
        }
    });

    // Stop recording
    stopButton.addEventListener('click', () => {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            recordingSection.classList.remove('recording');
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
        }
    });
}); 