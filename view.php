<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTML Viewer</title>
    <style>
        body {
            display: flex;
            margin: 0;
            height: 100vh;
            font-family: Arial, sans-serif;
        }
        .left-panel {
            width: 300px;
            background: #f0f0f0;
            border-right: 1px solid #ccc;
            overflow-y: auto;
            padding: 10px;
        }
        .left-panel .header {
            position: sticky;
            top: 0;
            background: #f0f0f0;
            z-index: 1;
            padding: 5px 0;
        }
        .left-panel input {
            width: calc(100% - 10px);
            padding: 5px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        .left-panel ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .left-panel ul li {
            margin: 5px 0;
        }
        .left-panel ul li button {
            width: 100%;
            padding: 5px 10px;
            border: none;
            background-color: #007bff;
            color: white;
            cursor: pointer;
            text-align: left;
        }
        .left-panel ul li button:hover {
            background-color: #0056b3;
        }
        .right-panel {
            flex: 1;
            height: calc(100% - 10px);
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
    <script>
        async function filterFiles() {
            const searchTerm = document.getElementById('search-input').value;

            // Send the search term to the backend
            const response = await fetch(`getFileContentMatches.php?term=${encodeURIComponent(searchTerm)}`);
            const data = await response.json();

            const fileList = document.getElementById('file-list');
            fileList.innerHTML = ''; // Clear the list

            if (data.status === 'success' && data.files.length > 0) {
                data.files.forEach(file => {
                    const listItem = document.createElement('li');
                    const button = document.createElement('button');
                    button.textContent = file;
                    button.onclick = () => loadHtml(file);
                    listItem.appendChild(button);
                    fileList.appendChild(listItem);
                });
            } else {
                const noResults = document.createElement('li');
                noResults.textContent = 'No matching files found.';
                fileList.appendChild(noResults);
            }
        }

        function loadHtml(file) {
            const iframe = document.querySelector('iframe[name="content-frame"]');
            iframe.src = `./output/${file}`;
        }
    </script>
</head>
<body>
    <div class="left-panel">
        <div class="header">
            <input
                type="text"
                id="search-input"
                placeholder="Search file contents..."
                oninput="filterFiles()"
            />
        </div>
        <ul id="file-list">
            <?php
            // Directory containing HTML files
            $directory = './output';
            $htmlFiles = array_filter(scandir($directory), function ($file) use ($directory) {
                return is_file($directory . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'html';
            });
            foreach ($htmlFiles as $file): ?>
                <li>
                    <button onclick="loadHtml('<?= htmlspecialchars($file) ?>')">
                        <?= htmlspecialchars($file) ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="right-panel">
        <iframe name="content-frame" src=""></iframe>
    </div>
</body>
</html>
