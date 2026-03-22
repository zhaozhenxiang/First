<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Error</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: #252526;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f48771;
        }
        .exception {
            color: #f48771;
            font-weight: bold;
            font-size: 20px;
        }
        .message {
            margin-top: 10px;
            font-size: 16px;
        }
        .section {
            background: #252526;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .section-title {
            color: #4ec9b0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            border-bottom: 1px solid #3e3e42;
            padding-bottom: 10px;
        }
        .location {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 4px;
            color: #9cdcfe;
            font-size: 14px;
        }
        .trace {
            list-style: none;
        }
        .trace li {
            padding: 10px;
            border-bottom: 1px solid #3e3e42;
            font-size: 13px;
        }
        .trace li:last-child {
            border-bottom: none;
        }
        .trace-file {
            color: #9cdcfe;
        }
        .trace-line {
            color: #b5cea8;
        }
        .trace-class {
            color: #4ec9b0;
        }
        .trace-function {
            color: #dcdcaa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="exception"><?php echo htmlspecialchars($exception); ?> - Error <?php echo $status; ?></div>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        </div>

        <div class="section">
            <div class="section-title">Location</div>
            <div class="location">
                <?php echo htmlspecialchars($file); ?>:<?php echo $line; ?>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Stack Trace</div>
            <ul class="trace">
                <?php
                $traceLines = explode("\n", $trace);
                foreach ($traceLines as $traceLine):
                    $traceLine = trim($traceLine);
                    if (empty($traceLine)) continue;

                    // 高亮显示
                    $traceLine = preg_replace('/#(\d+)/', '<span class="trace-line">#$1</span>', $traceLine);
                    $traceLine = preg_replace('/([a-zA-Z0-9_]+)::/', '<span class="trace-class">$1</span>::', $traceLine);
                    $traceLine = preg_replace('/([a-zA-Z0-9_]+)\(\)/', '<span class="trace-function">$1()</span>', $traceLine);
                    $traceLine = preg_replace('/\((.*?)\)/', '(<span style="color:ce9178;">$1</span>)', $traceLine);
                ?>
                    <li><?php echo htmlspecialchars($traceLine); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>
</html>
