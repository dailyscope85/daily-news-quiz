<?php
// --------------------
// 1️⃣ Database connection
// --------------------
$host = getenv('sql200.infinityfree.com');
$user = getenv('if0_39884715');
$pass = getenv('00Oq6VfU9YF');
$db   = getenv('if0_39884715_XXX');

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);

// --------------------
// 2️⃣ Fetch latest 5 news
// --------------------
$result = $conn->query("
    SELECT title, description 
    FROM news 
    ORDER BY publishedAt DESC 
    LIMIT 5
");

$newsText = "";
while ($row = $result->fetch_assoc()) {
    $content = $row['description'] ?? '';
    $newsText .= $row['title'] . " - " . $content . "\n";
}

// --------------------
// 3️⃣ Hugging Face API for quiz generation
// --------------------
$apiKey = getenv('HUGGINGFACE_API_KEY'); // ✅ reads the token securely
$prompt = "Generate 5 multiple-choice quiz questions based on these news articles:\n$newsText
Format:
1. Question?
A. Option 1
B. Option 2
C. Option 3
D. Option 4
Answer: <letter>";

// Choose a free Hugging Face model (GPT-Neo or GPT-2)
$model = "EleutherAI/gpt-neo-125M"; 

$ch = curl_init("https://api-inference.huggingface.co/models/$model");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        "inputs" => $prompt,
        "parameters" => ["max_new_tokens" => 300]
    ])
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

// Some Hugging Face responses wrap text differently
if (isset($data[0]['generated_text'])) {
    $quizText = $data[0]['generated_text'];
} else {
    $quizText = "No quiz generated.";
}

// --------------------
// 4️⃣ Save quiz locally
// --------------------
file_put_contents('daily_quiz.txt', trim($quizText));
echo "Quiz generated!\n";

// --------------------
// 5️⃣ Upload quiz to InfinityFree via FTP
// --------------------
$ftp_server = getenv('ftpupload.net');
$ftp_user   = getenv('if0_39884715');
$ftp_pass   = getenv('00Oq6VfU9YF');
$local_file = "daily_news_quiz.txt";
$remote_file = "/htdocs/daily_news_quiz.txt"; // adjust if your folder is different

$conn_id = ftp_connect($ftp_server);
$login = ftp_login($conn_id, $ftp_user, $ftp_pass);

if ($login && ftp_put($conn_id, $remote_file, $local_file, FTP_ASCII)) {
    echo "Quiz uploaded to InfinityFree successfully!\n";
} else {
    echo "FTP upload failed.\n";
}
ftp_close($conn_id);

$conn->close();
?>
