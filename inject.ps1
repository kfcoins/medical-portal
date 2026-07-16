$files = Get-ChildItem -Path "c:\xampp\htdocs\Mansro\frontend" -Filter "*.html" | Where-Object { 
    $_.Name -notin @("index.html", "login.html", "register.html", "forgot-password.html") 
}

foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    
    # Check if already injected
    if ($content -notmatch "responsive.css") {
        $replacement = "  <link rel=`"stylesheet`" href=`"css/responsive.css`">`n  <script src=`"js/responsive.js`"></script>`n</head>"
        $content = $content -replace "(?i)</head>", $replacement
        Set-Content -Path $file.FullName -Value $content -Encoding UTF8
        Write-Host "Updated $($file.Name)"
    } else {
        Write-Host "Skipped $($file.Name) - already has responsive.css"
    }
}
