document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('recordingForm');
    const recordButton = document.getElementById('startRecording');
    const recordingSection = document.getElementById('recordingSection');
    const uploadSection = document.getElementById('uploadSection');
    const textSection = document.getElementById('textSection');
    const textContent = document.getElementById('textContent');
    const contactPhone = document.getElementById('contactPhone');
    const audioFile = document.getElementById('audioFile');
    const submitButton = form.querySelector('button[type="submit"]');
    let mediaRecorder;
    let audioChunks = [];
    let hasRecording = false;
    let recordingStartTime;
    let recordingDuration = 0;
    let recordingTimer;
    let recordedAudioBlob = null;
    let recordingPrepIndicator = null;
    let isRecording = false;
    let recordingInProgress = false; // Prevent double clicks

    // Store original button content for restoration
    let originalRecordButtonContent = recordButton.innerHTML;

    // Force visibility of record button
    function ensureRecordButtonVisibility() {
        if (recordButton) {
            recordButton.style.display = 'inline-block !important';
            recordButton.classList.remove('d-none');
            recordButton.setAttribute('style', 'display: inline-block !important; visibility: visible !important;');
        }
        
        if (recordingSection) {
            recordingSection.classList.add('active');
            recordingSection.style.display = 'block !important';
            recordingSection.style.opacity = '1 !important';
            recordingSection.style.height = 'auto !important';
        }
    }

    // Call visibility function immediately and on page load
    ensureRecordButtonVisibility();
    window.addEventListener('load', ensureRecordButtonVisibility);

    // Validate that all required elements exist
    if (!form || !recordButton || !recordingSection || 
        !uploadSection || !textSection || !textContent || !contactPhone || !audioFile) {
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

    // Remove external timer display
    let recordingTimerDisplay = document.getElementById('recordingTimerDisplay');
    if (recordingTimerDisplay) {
        recordingTimerDisplay.remove();
    }

    // Function to format time in MM:SS format
    function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    // Function to create audio player
    function createAudioPlayer(audioBlob) {
        const audioUrl = URL.createObjectURL(audioBlob);
        const audioPlayback = document.getElementById('audioPlayback');
        const deleteButton = document.getElementById('deleteRecording');
        const startRecordingButton = document.getElementById('startRecording');

        if (!audioPlayback || !deleteButton || !startRecordingButton) {
            return;
        }

        // Show audio playback and set source
        audioPlayback.src = audioUrl;
        audioPlayback.classList.remove('hidden');

        // Show delete button, hide start recording button
        deleteButton.classList.remove('d-none');
        startRecordingButton.classList.add('d-none');

        // Add delete recording functionality
        const deleteHandler = () => {
            // Hide audio playback
            hideAudioPlayback();
            
            // Reset file input
            const fileInput = document.getElementById('audioFile');
            if (fileInput) {
                fileInput.value = '';
            }
            
            // Hide delete button, show start recording button
            deleteButton.classList.add('d-none');
            startRecordingButton.classList.remove('d-none');

            // Remove the event listener to prevent multiple bindings
            deleteButton.removeEventListener('click', deleteHandler);
        };

        // Add the event listener
        deleteButton.addEventListener('click', deleteHandler);
    }

    // Function to hide audio playback (if needed)
    function hideAudioPlayback() {
        const audioPlayback = document.getElementById('audioPlayback');
        const deleteButton = document.getElementById('deleteRecording');
        const startRecordingButton = document.getElementById('startRecording');

        // Hide audio playback
        audioPlayback.src = '';
        audioPlayback.classList.add('hidden');

        // Hide delete button, show start recording button
        if (deleteButton) {
            deleteButton.classList.add('d-none');
        }
        if (startRecordingButton) {
            startRecordingButton.classList.remove('d-none');
        }
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
        const formData = new FormData(form);
        
        if (selectedMethod === 'record') {
            if (!hasRecording || !recordedAudioBlob) {
                showNotification(t('recordingRequired'), 'error');
                return;
            }
            // Remove any existing audioFile from FormData
            formData.delete('audioFile');
            // Append the recorded audio as a file
            formData.append('audioFile', recordedAudioBlob, 'recording.wav');
        } else if (selectedMethod === 'upload') {
            if (!audioFile.files.length) {
                showNotification(t('fileRequired'), 'error');
                return;
            }
        } else if (selectedMethod === 'text') {
            if (!textContent.value.trim()) {
                showNotification(t('textRequired'), 'error');
                return;
            }
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
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${t('sending')}`;

        try {
            const response = await fetch('process.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.success) {
                showNotification(result.message, 'info');
                // Manually clear fields that should not be remembered
                textContent.value = '';
                audioFile.value = ''; // Clear file input
                const fileNameSpan = document.getElementById('fileName');
                if (fileNameSpan) fileNameSpan.textContent = '';
                form.classList.remove('was-validated');
                hasRecording = false;
                
                // Clear recording elements
                const audioPlayback = document.getElementById('audioPlayback');
                if (audioPlayback) {
                    audioPlayback.src = '';
                    audioPlayback.classList.add('hidden');
                }
                
                // Remove delete button if it exists
                const deleteButton = document.getElementById('deleteRecording');
                if (deleteButton) {
                    deleteButton.remove();
                }
            } else {
                showNotification(result.message, 'error');
            }
        } catch (error) {
            showNotification(t('errorSending'), 'error');
        } finally {
            // Re-enable submit button
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    });

    // Handle input method changes with additional visibility checks
    document.querySelectorAll('input[name="inputMethod"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove active class and hide all sections
            [recordingSection, uploadSection, textSection].forEach(section => {
                if (section) {
                    section.classList.remove('active');
                    section.style.display = 'none';
                }
            });

            // Reset required attributes
            audioFile.required = false;
            textContent.required = false;

            // Add active class to selected section and set required attribute
            switch(this.value) {
                case 'record':
                    if (recordingSection) {
                        recordingSection.classList.add('active');
                        recordingSection.style.display = 'block';
                        
                        // Ensure record button is visible
                        const startRecordingButton = document.getElementById('startRecording');
                        const deleteRecordingButton = document.getElementById('deleteRecording');
                        const audioPlayback = document.getElementById('audioPlayback');
                        
                        if (startRecordingButton) {
                            startRecordingButton.style.display = 'inline-block';
                            startRecordingButton.classList.remove('d-none');
                        }
                        
                        if (deleteRecordingButton) {
                            deleteRecordingButton.classList.add('d-none');
                        }
                        
                        if (audioPlayback) {
                            audioPlayback.classList.add('hidden');
                            audioPlayback.src = '';
                        }
                        
                        // Reset recording state
                        hasRecording = false;
                        audioChunks = [];
                    }
                    break;
                case 'upload':
                    if (uploadSection) {
                        uploadSection.classList.add('active');
                        uploadSection.style.display = 'block';
                        audioFile.required = true;
                    }
                    break;
                case 'text':
                    if (textSection) {
                        textSection.classList.add('active');
                        textSection.style.display = 'block';
                        textContent.required = true;
                    }
                    break;
            }
        });
    });

    // Get max recording duration from button data attribute
    const maxRecordingDuration = parseInt(recordButton.getAttribute('data-max-duration'), 10) || 60;

    // Start recording event listener
    async function recordingClickHandler(event) {
        // If currently recording, allow stop immediately
        if (isRecording) {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
                clearInterval(recordingTimer);
                recordingSection.classList.remove('recording');
            }
            // Do not set recordingInProgress here, onstop will handle it
            return;
        }

        // If already starting, prevent double start
        if (recordingInProgress) {
            return;
        }
        recordingInProgress = true; // Only set here, not at the very top

        recordButton.disabled = true;

        // Show preparation indicator inside the button
        const currentLang = document.querySelector('.language-switcher button.active').dataset.lang;
        recordButton.innerHTML = `<i class='fas fa-spinner fa-spin'></i> <span data-i18n='preparingRecording'>${t('preparingRecording', currentLang)}</span>`;

        const permissionGranted = await requestMicrophonePermission();
        if (!permissionGranted) {
            // Restore button if permission denied
            recordButton.innerHTML = originalRecordButtonContent;
            recordButton.disabled = false;
            isRecording = false;
            recordingInProgress = false;
            return;
        }

        // Show max duration warning when starting recording
        showNotification(t('maxDurationWarning', currentLang).replace('{duration}', maxRecordingDuration), 'info');

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

            // Restore button when recording actually starts, with timer
            let elapsed = 0;
            recordButton.innerHTML = `<i class="fas fa-stop"></i> <span data-i18n="stopRecording">${t('stopRecording', currentLang)}</span> <span id="recordingTime" style="margin-left:0.5rem;font-weight:bold;color:#ffc107;">00:00</span>`;
            recordButton.classList.add('btn-danger');
            recordButton.classList.remove('btn-primary');
            recordButton.disabled = false; // Re-enable button after successful start

            hasRecording = false;
            recordingStartTime = Date.now();
            recordingDuration = 0;

            // Show recording controls
            recordingSection.classList.add('recording');

            // Start recording
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.start();

            mediaRecorder.ondataavailable = (event) => {
                audioChunks.push(event.data);
            };

            // Set state to recording
            isRecording = true;

            // Set timeout to stop recording at max duration
            let maxDurationTimeout = setTimeout(() => {
                if (mediaRecorder && mediaRecorder.state === 'recording') {
                    mediaRecorder.stop();
                    clearInterval(recordingTimer);
                    recordingSection.classList.remove('recording');
                    const currentLang = document.querySelector('.language-switcher button.active').dataset.lang;
                    showNotification(t('maxDurationReached', currentLang), 'error');
                }
            }, maxRecordingDuration * 1000);

            mediaRecorder.onstop = async () => {
                recordingInProgress = false; // Reset flag immediately
                clearTimeout(maxDurationTimeout);
                const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                if (audioBlob.size > 0) {
                    hasRecording = true;
                    recordedAudioBlob = audioBlob;
                    createAudioPlayer(audioBlob);
                }
                stream.getTracks().forEach(track => track.stop());
                isRecording = false;
                // Restore button text (remove timer)
                recordButton.innerHTML = `<i class="fas fa-microphone"></i> <span id="recordButtonText" data-i18n="startRecording">${t('startRecording', currentLang)}</span>`;
                recordButton.classList.remove('btn-danger');
                recordButton.classList.add('btn-primary');
                recordButton.disabled = false; // Re-enable button after stop
            };

            // Timer logic inside the button
            function updateButtonTimer() {
                elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
                const timerSpan = recordButton.querySelector('#recordingTime');
                if (timerSpan) {
                    timerSpan.textContent = formatTime(elapsed);
                }
            }
            recordingTimer = setInterval(updateButtonTimer, 1000);
            // Set initial timer
            updateButtonTimer();

        } catch (err) {
            // Restore button on error
            recordButton.innerHTML = originalRecordButtonContent;
            recordButton.disabled = false;
            isRecording = false;
            recordingInProgress = false; // Ensure flag is reset on error
            showNotification(t('recordingError'), 'error');
        }
    }

    // Attach the handler once on page load
    recordButton.addEventListener('click', recordingClickHandler);

    // Stop recording function
    function stopRecording() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
            clearInterval(recordingTimer);
            recordingSection.classList.remove('recording');
        }
    }

    // Add event listener for delete recording button
    const deleteButton = document.getElementById('deleteRecording');
    if (deleteButton) {
        deleteButton.addEventListener('click', () => {
            hideAudioPlayback();
            deleteButton.classList.add('d-none');
            recordButton.classList.remove('d-none');
            audioChunks = [];
            hasRecording = false;
        });
    }

    // Drag & Drop functionality
    const cardBody = document.querySelector('.card-body');
    let dragCounter = 0;
    let dragTimeout = null;
    
    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        cardBody.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    // Highlight drop area when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        cardBody.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        cardBody.addEventListener(eventName, unhighlight, false);
    });

    // Handle dropped files
    cardBody.addEventListener('drop', handleDrop, false);

    // Add global drag end listener to ensure cleanup
    document.addEventListener('dragend', unhighlight, false);

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        e.preventDefault();
        dragCounter++;
        
        // Clear any existing timeout
        if (dragTimeout) {
            clearTimeout(dragTimeout);
            dragTimeout = null;
        }
        
        if (dragCounter === 1) {
            cardBody.classList.add('drag-over');
            // Always use current translation
            cardBody.setAttribute('data-drop-text', t('dropAudioFilesHere'));
        }
    }

    function unhighlight(e) {
        e.preventDefault();
        dragCounter--;
        
        // Clear any existing timeout
        if (dragTimeout) {
            clearTimeout(dragTimeout);
        }
        
        if (dragCounter <= 0) {
            dragCounter = 0;
            cardBody.classList.remove('drag-over');
        } else {
            // Set a timeout to force clear the drag state if events get stuck
            dragTimeout = setTimeout(() => {
                dragCounter = 0;
                cardBody.classList.remove('drag-over');
                dragTimeout = null;
            }, 100);
        }
    }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;

        if (files.length > 0) {
            // Filter for audio files only
            const audioFiles = Array.from(files).filter(file => {
                const validTypes = [
                    'audio/mpeg', 'audio/wav', 'audio/mp4', 'audio/webm', 
                    'audio/ogg', 'audio/aac', 'audio/x-m4a', 'audio/mp3'
                ];
                return validTypes.includes(file.type) || 
                       file.name.match(/\.(mp3|wav|mp4|webm|ogg|aac|m4a)$/i);
            });

            if (audioFiles.length > 0) {
                // Switch to upload method
                const uploadRadio = document.getElementById('uploadAudio');
                if (uploadRadio) {
                    uploadRadio.checked = true;
                    uploadRadio.dispatchEvent(new Event('change'));
                }

                // Set the files to the file input
                const fileInput = document.getElementById('audioFile');
                if (fileInput) {
                    // Create a new DataTransfer object
                    const dataTransfer = new DataTransfer();
                    audioFiles.forEach(file => dataTransfer.items.add(file));
                    fileInput.files = dataTransfer.files;
                    
                    // Trigger change event to update any listeners
                    fileInput.dispatchEvent(new Event('change'));
                }

                // Show success notification
                const fileNames = audioFiles.map(f => f.name).join(', ');
                // Get current language from active button
                const activeLangButton = document.querySelector('.language-switcher button.active');
                const currentLang = activeLangButton ? activeLangButton.dataset.lang : 'en';
                showNotification(`${t('filesUploaded', currentLang)}: ${fileNames}`, 'info');
            } else {
                const activeLangButton = document.querySelector('.language-switcher button.active');
                const currentLang = activeLangButton ? activeLangButton.dataset.lang : 'en';
                showNotification(t('dropAudioFilesOnly', currentLang), 'error');
            }
        }
    }

    // Hide audio playback on page load
    document.addEventListener('DOMContentLoaded', () => {
        const audioPlayback = document.getElementById('audioPlayback');
        if (audioPlayback) {
            audioPlayback.classList.add('hidden');
        }
    });

    // Custom file input logic
    const audioFileInput = document.getElementById('audioFile');
    const customFileLabel = document.getElementById('customFileLabel');
    const fileNameSpan = document.getElementById('fileName');
    if (audioFileInput && customFileLabel && fileNameSpan) {
        audioFileInput.addEventListener('change', function() {
            fileNameSpan.textContent = this.files[0] ? this.files[0].name : '';
        });
    }
}); 