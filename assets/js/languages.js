const translations = {
    en: {
        // Recording Method
        recordingMethod: "Recording Method",
        recordAudio: "Record Audio",
        uploadAudio: "Upload Audio",
        textInput: "Text Input",
        
        // Recording Controls
        startRecording: "Start Recording",
        stopRecording: "Stop Recording",
        recordingDuration: "Recording duration",
        
        // Form Labels
        companyName: "Company Name",
        contactEmail: "Contact Email",
        contactPhone: "Contact Phone",
        notes: "Notes",
        severityLevel: "Severity Level",
        requiredFields: "Required fields",
        
        // Severity Levels
        normal: "Normal",
        high: "High",
        emergency: "Emergency",
        normalDescription: "Handle in a few business days",
        highDescription: "Must be done before tomorrow",
        emergencyDescription: "Must be done ASAP",
        
        // Form Placeholders
        companyNamePlaceholder: "Add your company name",
        emailPlaceholder: "Add your e-mail address",
        phonePlaceholder: "Add your phone number",
        notesPlaceholder: "Specify when the recording should be played (date and time) and any other relevant information. And if appllicable, add the external phone number for which the audio recording is e.g. +31 99 999 999.",
        
        // File Upload
        uploadAudioFile: "Upload Audio File",
        supportedFormats: "Supported formats: MP3, WAV, MP4, WebM",
        
        // Text Input
        enterText: "Enter Text",
        
        // Buttons
        submit: "Submit",
        delete: "Delete",
        
        // Messages
        businessHoursNote: "Please note that these requests will only be handled between business hours.",
        forAssistanceContact: "For assistance, contact",
        dragDropHint: "💡 Tip: You can drag and drop audio files anywhere on this form",
        dropAudioFilesHere: "Drop audio files here",
        developedBy: "Developed by",
        
        // Notifications
        recordingNotSupported: "Your browser does not support audio recording. Please use a modern browser or try the upload option instead.",
        httpsRequired: "Audio recording requires a secure connection (HTTPS) or localhost.",
        allowMicrophone: "Please allow microphone access in your browser settings. Click the camera/microphone icon in the address bar to grant permission.",
        noMicrophone: "No microphone found. Please ensure your device has a microphone.",
        microphoneBusy: "Your microphone is busy or not working. Please try again or use the upload option.",
        recordingError: "Error processing the recording. Please try again.",
        microphoneError: "Error accessing microphone. Please try the upload option instead.",
        invalidEmail: "Please enter a valid email address.",
        invalidPhone: "Please enter a valid phone number (only numbers and + symbol allowed).",
        recordingRequired: "Please record a message before submitting.",
        fileRequired: "Please select an audio file to upload.",
        textRequired: "Please enter some text before submitting.",
        sending: "Sending...",
        errorSending: "An error occurred while sending your submission. Please try again.",
        submissionSuccess: "Your submission has been received successfully.",
        filesUploaded: "Files uploaded",
        dropAudioFilesOnly: "Please drop audio files only (MP3, WAV, MP4, WebM, OGG, AAC, M4A)",
        submissionSuccess: "Your submission has been received successfully."
    },
    nl: {
        // Recording Method
        recordingMethod: "Opnamemethode",
        recordAudio: "Audio Opnemen",
        uploadAudio: "Audio Uploaden",
        textInput: "Tekst Invoer",
        
        // Recording Controls
        startRecording: "Start Opname",
        stopRecording: "Stop Opname",
        recordingDuration: "Opnameduur",
        
        // Form Labels
        companyName: "Bedrijfsnaam",
        contactEmail: "Contact E-mail",
        contactPhone: "Contact Telefoon",
        notes: "Notities",
        severityLevel: "Prioriteitsniveau",
        requiredFields: "Vereiste velden",
        
        // Severity Levels
        normal: "Normaal",
        high: "Hoog",
        emergency: "Spoed",
        normalDescription: "Afhandeling binnen enkele werkdagen",
        highDescription: "Moet voor morgen afgehandeld worden",
        emergencyDescription: "Moet direct afgehandeld worden",
        
        // Form Placeholders
        companyNamePlaceholder: "Voer uw bedrijfsnaam in",
        emailPlaceholder: "Voer uw e-mailadres in",
        phonePlaceholder: "Voer uw telefoonnummer in",
        notesPlaceholder: "Geef aan wanneer de opname moet worden afgespeeld (datum en tijd) en eventuele andere relevante informatie. En indien van toepassing, voeg het externe telefoonnummer toe waarvoor de audio-opname is, bijv. +31 99 999 999.",
        
        // File Upload
        uploadAudioFile: "Audio Bestand Uploaden",
        supportedFormats: "Ondersteunde formaten: MP3, WAV, MP4, WebM",
        
        // Text Input
        enterText: "Voer Tekst In",
        
        // Buttons
        submit: "Versturen",
        delete: "Verwijderen",
        
        // Messages
        businessHoursNote: "Let op: deze aanvragen worden alleen tijdens kantooruren behandeld.",
        forAssistanceContact: "Voor hulp, stuur een mail naar",
        dragDropHint: "💡 Tip: U kunt audiobestanden plaatsen op deze vorm",
        dropAudioFilesHere: "Plaats audiobestanden hier",
        developedBy: "Ontwikkeld door",
        
        // Notifications
        recordingNotSupported: "Uw browser ondersteunt geen audio-opname. Gebruik een moderne browser of probeer de upload-optie.",
        httpsRequired: "Audio-opname vereist een beveiligde verbinding (HTTPS) of localhost.",
        allowMicrophone: "Sta microfoon-toegang toe in uw browser-instellingen. Klik op het camera/microfoon-pictogram in de adresbalk om toestemming te verlenen.",
        noMicrophone: "Geen microfoon gevonden. Zorg ervoor dat uw apparaat een microfoon heeft.",
        microphoneBusy: "Uw microfoon is bezet of werkt niet. Probeer het opnieuw of gebruik de upload-optie.",
        recordingError: "Fout bij het verwerken van de opname. Probeer het opnieuw.",
        microphoneError: "Fout bij toegang tot microfoon. Probeer de upload-optie.",
        invalidEmail: "Voer een geldig e-mailadres in.",
        invalidPhone: "Voer een geldig telefoonnummer in (alleen cijfers en + symbool toegestaan).",
        recordingRequired: "Maak eerst een opname voordat u verstuurt.",
        fileRequired: "Selecteer eerst een audiobestand voordat u verstuurt.",
        textRequired: "Voer eerst tekst in voordat u verstuurt.",
        sending: "Versturen...",
        errorSending: "Er is een fout opgetreden bij het versturen van uw aanvraag. Probeer het opnieuw.",
        submissionSuccess: "Uw aanvraag is succesvol ontvangen.",
        filesUploaded: "Bestanden geüpload",
        dropAudioFilesOnly: "Plaats alleen audiobestanden (MP3, WAV, MP4, WebM, OGG, AAC, M4A)"
    }
};

// Function to get translation
function t(key, lang = 'en') {
    return translations[lang][key] || translations['en'][key] || key;
}

// Function to set language
function setLanguage(lang) {
    document.documentElement.lang = lang;
    updateTranslations(lang);
}

// Function to update all translations on the page
function updateTranslations(lang) {
    document.querySelectorAll('[data-i18n]').forEach(element => {
        const key = element.getAttribute('data-i18n');
        element.textContent = t(key, lang);
    });
    
    document.querySelectorAll('[data-i18n-placeholder]').forEach(element => {
        const key = element.getAttribute('data-i18n-placeholder');
        element.placeholder = t(key, lang);
    });
    
    // Update drag overlay text if currently active
    const cardBody = document.querySelector('.card-body');
    if (cardBody && cardBody.classList.contains('drag-over')) {
        cardBody.setAttribute('data-drop-text', t('dropAudioFilesHere', lang));
    }
}

// Add language selector to the page
function addLanguageSelector() {
    const selector = document.createElement('div');
    selector.className = 'language-selector mb-3';
    selector.innerHTML = `
        <select class="form-select" id="languageSelect">
            <option value="en">English</option>
            <option value="nl">Nederlands</option>
        </select>
    `;
    
    document.querySelector('.card-body').insertBefore(selector, document.querySelector('.card-body').firstChild);
    
    // Add event listener
    document.getElementById('languageSelect').addEventListener('change', (e) => {
        setLanguage(e.target.value);
    });
}

// Initialize language switcher
document.addEventListener('DOMContentLoaded', function() {
    const languageButtons = document.querySelectorAll('.language-switcher button');
    
    languageButtons.forEach(button => {
        button.addEventListener('click', function() {
            const lang = this.dataset.lang;
            setLanguage(lang);
            
            // Update active state
            languageButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Set default language
    setLanguage('en');
}); 