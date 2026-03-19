// Webcam functionality - loaded dynamically
document.addEventListener('DOMContentLoaded', function() {
    const useWebcamBtn = document.getElementById('useWebcam');
    const switchToFileBtn = document.getElementById('switchToFile');
    const webcamModal = document.getElementById('webcamModal');
    const webcamContainer = document.getElementById('webcamContainer');
    const closeWebcamModalBtn = document.getElementById('closeWebcamModal');
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const captureBtn = document.getElementById('capture');
    const retakeBtn = document.getElementById('retake');
    const switchCameraBtn = document.getElementById('switchCamera');
    const cancelWebcamBtn = document.getElementById('cancelWebcam');
    const photoPreview = document.getElementById('photoPreview');
    const photoInput = document.getElementById('photo');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');
    const cameraSelect = document.getElementById('cameraSelect');
    const startCameraBtn = document.getElementById('startCamera');

    let stream;
    let currentFacingMode = 'user'; // 'user' for front camera, 'environment' for back
    let availableDevices = [];
    let selectedDeviceId = null;

    // Get available video devices
    async function getVideoDevices() {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            availableDevices = devices.filter(device => device.kind === 'videoinput');
            return availableDevices.length > 1;
        } catch (err) {
            console.error('Error enumerating devices:', err);
            return false;
        }
    }

    // Populate camera select dropdown
    async function populateCameraSelect() {
        try {
            // Request permission first to get device labels
            await navigator.mediaDevices.getUserMedia({ video: true });
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(device => device.kind === 'videoinput');

            cameraSelect.innerHTML = '<option value="">Select Camera</option>';
            videoDevices.forEach(device => {
                const option = document.createElement('option');
                option.value = device.deviceId;
                option.textContent = device.label || `Camera ${cameraSelect.options.length}`;
                cameraSelect.appendChild(option);
            });

            if (videoDevices.length > 0) {
                cameraSelect.style.display = 'block';
                startCameraBtn.style.display = 'inline-block';
                loadingOverlay.style.display = 'none';
                loadingOverlay.textContent = 'Please wait while we access your camera...';
            } else {
                errorMessage.textContent = 'No cameras found on this device.';
                errorMessage.style.display = 'block';
                loadingOverlay.style.display = 'none';
            }
        } catch (err) {
            console.error('Error populating camera select:', err);
            errorMessage.textContent = 'Error accessing camera devices. Please check permissions.';
            errorMessage.style.display = 'block';
            loadingOverlay.style.display = 'none';
        }
    }

    // Start webcam with constraints
    async function startWebcam(facingMode = 'user') {
        try {
            loadingOverlay.style.display = 'flex';
            loadingOverlay.textContent = 'Initializing camera...';
            errorMessage.style.display = 'none';
            successMessage.style.display = 'none';

            // Check if getUserMedia is supported
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('getUserMedia is not supported on this browser');
            }

            const constraints = {
                video: {
                    width: { ideal: 1280, min: 640 },
                    height: { ideal: 720, min: 480 },
                    facingMode: facingMode
                }
            };

            // Add a small delay to prevent rapid initialization
            await new Promise(resolve => setTimeout(resolve, 100));

            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }

            stream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = stream;

            video.onloadedmetadata = function() {
                loadingOverlay.style.display = 'none';
                successMessage.textContent = 'Camera ready! Click "Capture Photo" to take a picture.';
                successMessage.style.display = 'block';
            };

            // Check if multiple cameras are available
            const hasMultipleCameras = await getVideoDevices();
            switchCameraBtn.style.display = hasMultipleCameras ? 'inline-block' : 'none';

        } catch (err) {
            loadingOverlay.style.display = 'none';
            let errorMsg = 'Error accessing webcam. ';
            if (err.name === 'NotAllowedError') {
                errorMsg += 'Camera access denied. Please allow camera permissions.';
            } else if (err.name === 'NotFoundError') {
                errorMsg += 'No camera found on this device.';
            } else if (err.name === 'NotReadableError') {
                errorMsg += 'Camera is already in use by another application.';
            } else if (err.name === 'NotSupportedError') {
                errorMsg += 'Camera not supported on this browser.';
            } else if (err.name === 'AbortError') {
                errorMsg += 'Camera access was interrupted.';
            } else if (err.message.includes('getUserMedia is not supported')) {
                errorMsg += 'Camera not supported on this browser.';
            } else {
                errorMsg += err.message;
            }
            errorMessage.textContent = errorMsg;
            errorMessage.style.display = 'block';
            console.error('Webcam error:', err);
        }
    }

    // Start webcam with specific device
    async function startWebcamWithDevice(deviceId) {
        try {
            loadingOverlay.style.display = 'flex';
            loadingOverlay.textContent = 'Initializing camera...';
            errorMessage.style.display = 'none';
            successMessage.style.display = 'none';

            // Check if getUserMedia is supported
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('getUserMedia is not supported on this browser');
            }

            const constraints = {
                video: {
                    deviceId: { exact: deviceId },
                    width: { ideal: 1280, min: 640 },
                    height: { ideal: 720, min: 480 }
                }
            };

            // Add a small delay to prevent rapid initialization
            await new Promise(resolve => setTimeout(resolve, 100));

            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }

            stream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = stream;

            video.onloadedmetadata = function() {
                loadingOverlay.style.display = 'none';
                successMessage.textContent = 'Camera ready! Click "Capture Photo" to take a picture.';
                successMessage.style.display = 'block';
            };

            // Check if multiple cameras are available
            const hasMultipleCameras = await getVideoDevices();
            switchCameraBtn.style.display = hasMultipleCameras ? 'inline-block' : 'none';

        } catch (err) {
            loadingOverlay.style.display = 'none';
            let errorMsg = 'Error accessing webcam. ';
            if (err.name === 'NotAllowedError') {
                errorMsg += 'Camera access denied. Please allow camera permissions.';
            } else if (err.name === 'NotFoundError') {
                errorMsg += 'No camera found on this device.';
            } else if (err.name === 'NotReadableError') {
                errorMsg += 'Camera is already in use by another application.';
            } else if (err.name === 'NotSupportedError') {
                errorMsg += 'Camera not supported on this browser.';
            } else if (err.name === 'AbortError') {
                errorMsg += 'Camera access was interrupted.';
            } else if (err.message.includes('getUserMedia is not supported')) {
                errorMsg += 'Camera not supported on this browser.';
            } else {
                errorMsg += err.message;
            }
            errorMessage.textContent = errorMsg;
            errorMessage.style.display = 'block';
            console.error('Webcam error:', err);
        }
    }

    useWebcamBtn.addEventListener('click', async function() {
        // Show modal and loading overlay immediately
        webcamModal.style.display = 'flex';
        loadingOverlay.style.display = 'flex';
        loadingOverlay.textContent = 'Scanning for available cameras...';
        useWebcamBtn.style.display = 'none';
        photoInput.style.display = 'none';

        // Populate camera select dropdown
        await populateCameraSelect();
    });

    startCameraBtn.addEventListener('click', async function() {
        selectedDeviceId = cameraSelect.value;
        if (!selectedDeviceId) {
            errorMessage.textContent = 'Please select a camera.';
            errorMessage.style.display = 'block';
            return;
        }

        // Hide camera select and start button
        cameraSelect.style.display = 'none';
        startCameraBtn.style.display = 'none';

        // Start webcam with selected device
        await startWebcamWithDevice(selectedDeviceId);
    });

    switchToFileBtn.addEventListener('click', function() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        webcamModal.style.display = 'none';
        useWebcamBtn.style.display = 'inline-block';
        switchToFileBtn.style.display = 'none';
        photoInput.style.display = 'block';
        photoPreview.innerHTML = '';
        video.style.display = 'block';
        captureBtn.style.display = 'inline-block';
        retakeBtn.style.display = 'none';
        switchCameraBtn.style.display = 'none';
        errorMessage.style.display = 'none';
        successMessage.style.display = 'none';
    });

    closeWebcamModalBtn.addEventListener('click', function() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        webcamModal.style.display = 'none';
        useWebcamBtn.style.display = 'inline-block';
        switchToFileBtn.style.display = 'none';
        photoInput.style.display = 'block';
        photoPreview.innerHTML = '';
        video.style.display = 'block';
        captureBtn.style.display = 'inline-block';
        retakeBtn.style.display = 'none';
        switchCameraBtn.style.display = 'none';
        errorMessage.style.display = 'none';
        successMessage.style.display = 'none';
    });

    captureBtn.addEventListener('click', function() {
        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        video.style.display = 'none';
        captureBtn.style.display = 'none';
        retakeBtn.style.display = 'inline-block';
        switchCameraBtn.style.display = 'none';
        errorMessage.style.display = 'none';
        successMessage.textContent = 'Photo captured successfully! You can retake or proceed with this image.';
        successMessage.style.display = 'block';

        // Create high-quality image blob
        canvas.toBlob(function(blob) {
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const fileName = `photo_${timestamp}.jpg`;
            const file = new File([blob], fileName, { type: 'image/jpeg' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            photoInput.files = dataTransfer.files;

            // Show preview
            const img = document.createElement('img');
            img.src = canvas.toDataURL('image/jpeg', 0.9); // High quality
            photoPreview.innerHTML = '';
            photoPreview.appendChild(img);
        }, 'image/jpeg', 0.9); // High quality JPEG
    });

    retakeBtn.addEventListener('click', function() {
        video.style.display = 'block';
        captureBtn.style.display = 'inline-block';
        retakeBtn.style.display = 'none';
        const hasMultipleCameras = availableDevices.length > 1;
        switchCameraBtn.style.display = hasMultipleCameras ? 'inline-block' : 'none';
        photoPreview.innerHTML = '';
        photoInput.value = '';
        errorMessage.style.display = 'none';
        successMessage.textContent = 'Camera ready! Click "Capture Photo" to take a picture.';
        successMessage.style.display = 'block';
    });

    switchCameraBtn.addEventListener('click', async function() {
        currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
        await startWebcam(currentFacingMode);
    });

    cancelWebcamBtn.addEventListener('click', function() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        webcamModal.style.display = 'none';
        useWebcamBtn.style.display = 'inline-block';
        switchToFileBtn.style.display = 'none';
        photoInput.style.display = 'block';
        photoPreview.innerHTML = '';
        video.style.display = 'block';
        captureBtn.style.display = 'inline-block';
        retakeBtn.style.display = 'none';
        switchCameraBtn.style.display = 'none';
        errorMessage.style.display = 'none';
        successMessage.style.display = 'none';
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    });
});
