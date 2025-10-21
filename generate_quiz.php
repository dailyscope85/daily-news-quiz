<?php
// --------------------
// 1️⃣ Database connection
// --------------------
$host = getenv('DB_HOST');   // GitHub secret or local testing value
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);

// --------------------
// 2️⃣ Fetch latest 10 news
// --------------------
$result = $conn->query("
    SELECT title, category 
    FROM news 
    ORDER BY publishedAt DESC 
    LIMIT 10
");

$news = [];
while ($row = $result->fetch_assoc()) {
    $news[] = $row;
}
echo "Found " . count($news) . " news items.\n";
if (count($news) == 0) {
    die("No news found in the database.");
}

// --------------------
// 3️⃣ Generate 5 quiz questions
// --------------------
shuffle($news); // randomize news for variety
$quizText = "";
for ($i = 0; $i < 5 && $i < count($news); $i++) {
    $correct = $news[$i];
    
    // Pick 3 random wrong options from other news
    $wrongOptions = [];
    $others = array_filter($news, fn($n) => $n !== $correct);
    shuffle($others);
    for ($j = 0; $j < 3 && $j < count($others); $j++) {
        $wrongOptions[] = $others[$j]['title'];
    }

    // If less than 3 wrong options, fill with placeholders
    while (count($wrongOptions) < 3) {
        $wrongOptions[] = "Other news option";
    }

    // Combine correct + wrong options and shuffle
    $options = $wrongOptions;
    $options[] = $correct['title'];
    shuffle($options);

    // Determine correct letter
    $letters = ['A','B','C','D'];
    $correctLetter = '';
    foreach ($options as $index => $opt) {
        if ($opt === $correct['title']) $correctLetter = $letters[$index];
    }

    // Build question text
    $quizText .= ($i+1) . ". Which of the following is a recent news in the category '{$correct['category']}'?\n";
    foreach ($options as $index => $opt) {
        $quizText .= $letters[$index] . ". $opt\n";
    }
    $quizText .= "Answer: $correctLetter\n\n";
}

// --------------------
// 4️⃣ Save quiz locally
// --------------------
$local_file = __DIR__ . "/daily_news_quiz.txt";
file_put_contents($local_file, trim($quizText));
echo "Quiz generated successfully!\n";

// --------------------
// 5️⃣ Upload quiz to InfinityFree via FTP
// --------------------
$ftp_server = getenv('FTP_HOST');
$ftp_user   = getenv('FTP_USER');
$ftp_pass   = getenv('FTP_PASS');
$remote_file = "/htdocs/daily_news_quiz.txt";

$conn_id = ftp_connect($ftp_server);
if (!$conn_id) die("Could not connect to FTP server.");

$login = ftp_login($conn_id, $ftp_user, $ftp_pass);
if (!$login) die("FTP login failed.");

if (ftp_put($conn_id, $remote_file, $local_file, FTP_ASCII)) {
    echo "Quiz uploaded to InfinityFree successfully!\n";
} else {
    echo "FTP upload failed.\n";
}

ftp_close($conn_id);
$conn->close();
?>
