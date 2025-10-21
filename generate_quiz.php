<?php
// --------------------
// 1️⃣ Database connection
// --------------------
$host = getenv('DB_HOST');   // use the secret name
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');

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
$prompt = "You are a professional quiz maker. Generate 5 multiple-choice quiz questions based on the following news articles. Each question should be clear, meaningful, and directly related to the news content.

- Provide exactly 4 answer options labeled A, B, C, D.  
- Only one option should be correct.  
- Indicate the correct answer at the end.

Format the output exactly like this:

1. [Question text]
A. [Option A]
B. [Option B]
C. [Option C]
D. [Option D]
Answer: [letter]

News articles: 
$newsText"; // $newsText contains titles + descriptions from your DB

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
$ftp_server = getenv('FTP_HOST');
$ftp_user   = getenv('FTP_USER');
$ftp_pass   = getenv('FTP_PASS');
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
