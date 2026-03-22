<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>401 - Unauthorized</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            text-align: center;
            background: white;
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
        }
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .error-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 15px;
        }
        .error-message {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #fa709a;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            transition: all 0.3s;
            font-weight: 500;
        }
        .btn:hover {
            background: #e95a8a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(250, 112, 154, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">🔒</div>
        <h1 class="error-title">Unauthorized</h1>
        <p class="error-message">
            You need to log in to access this page.
        </p>
        <a href="/login" class="btn">Login</a>
    </div>
</body>
</html>
