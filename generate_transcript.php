<?php
function getAssemblyAITanscript($audioFilePath) {
    $apiKey = 'c7625337ac1d42c8b72802c3c9f3e883'; // Replace with your AssemblyAI API key
    $uploadUrl = 'https://api.assemblyai.com/v2/upload';
    $transcriptUrl = 'https://api.assemblyai.com/v2/transcript';

    // Determine the correct content type based on the file extension
    $fileExtension = pathinfo($audioFilePath, PATHINFO_EXTENSION);
    $contentType = 'audio/' . $fileExtension;

    // Upload audio file
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uploadUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($audioFilePath));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'authorization: ' . $apiKey,
        'content-type: ' . $contentType
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $uploadResponse = curl_exec($ch);
    if (curl_errno($ch)) {
        return json_encode(['error' => 'Curl error: ' . curl_error($ch)]);
    }
    curl_close($ch);

    $uploadResult = json_decode($uploadResponse, true);
    if (!isset($uploadResult['upload_url'])) {
        return json_encode(['error' => 'Upload error: ' . htmlspecialchars($uploadResponse)]);
    }

    $audioUrl = $uploadResult['upload_url'];

    // Request transcription
    $postData = json_encode([
        'audio_url' => $audioUrl,
        'speaker_labels' => true,
        'language_code' => 'hi' // Specify the language code for Hindi
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $transcriptUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'authorization: ' . $apiKey,
        'content-type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $transcriptResponse = curl_exec($ch);
    if (curl_errno($ch)) {
        return json_encode(['error' => 'Curl error: ' . curl_error($ch)]);
    }
    curl_close($ch);

    $transcriptResult = json_decode($transcriptResponse, true);
    if (!isset($transcriptResult['id'])) {
        return json_encode(['error' => 'Transcription request error: ' . htmlspecialchars($transcriptResponse)]);
    }

    // Polling for transcription status
    $transcriptId = $transcriptResult['id'];
    $pollingEndpoint = "https://api.assemblyai.com/v2/transcript/$transcriptId";
    
    while (true) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $pollingEndpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'authorization: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $statusResponse = curl_exec($ch);
        if (curl_errno($ch)) {
            return json_encode(['error' => 'Curl error: ' . curl_error($ch)]);
        }
        curl_close($ch);

        $statusResult = json_decode($statusResponse, true);
        if ($statusResult['status'] === 'completed') {
            return json_encode(['text' => $statusResult['text'] ?? 'No transcript available']);
        } elseif ($statusResult['status'] === 'error') {
            return json_encode(['error' => 'Transcription failed: ' . htmlspecialchars($statusResult['error'])]);
        } else {
            sleep(10); // Wait before polling again
        }
    }
}

if (isset($_POST['audio_file_path'])) {
    $audioFilePath = $_POST['audio_file_path'];
    header('Content-Type: application/json');
    echo getAssemblyAITanscript($audioFilePath);
}
?>
