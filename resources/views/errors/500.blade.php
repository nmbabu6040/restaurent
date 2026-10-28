<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 | {{ config('app.name') }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #FAF7F2;
            color: #292D32;
            font-family: system-ui, sans-serif;
            text-align: center;
            padding: 1.5rem;
        }

        h1 {
            color: #172B4D;
            font-family: Georgia, serif;
            margin: .5rem 0;
        }

        .code {
            font-family: Georgia, serif;
            font-size: 4.5rem;
            font-weight: 700;
            color: #B25A37;
            margin: 0;
        }

        a {
            display: inline-block;
            margin-top: 1.5rem;
            padding: .75rem 1.5rem;
            border-radius: 999px;
            background: #172B4D;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <main>
        <p class="code" aria-hidden="true">500</p>
        <h1>Something went wrong</h1>
        <p>We hit a problem on our side. Please try again in a moment.</p>
        <h1 lang="bn" style="font-size:1.25rem">কিছু একটা সমস্যা হয়েছে</h1>
        <p lang="bn">আমাদের দিকে একটি সমস্যা হয়েছে। একটু পর আবার চেষ্টা করুন।</p>
        <a href="/">Home / হোম</a>
    </main>
</body>

</html>
