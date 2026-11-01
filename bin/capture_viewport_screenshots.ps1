$chromePath = "C:\Program Files\Google\Chrome\Application\chrome.exe"
$baseDir = "C:\Users\hp\Desktop\Baymax_Voting_System\screenshots\responsive"

if (-not (Test-Path $baseDir)) {
    New-Item -ItemType Directory -Force -Path $baseDir | Out-Null
}

$viewports = @(
    @{ name = "home_320"; width = 320; height = 640; url = "http://localhost/Baymax_Voting_System/public/" },
    @{ name = "home_360"; width = 360; height = 740; url = "http://localhost/Baymax_Voting_System/public/" },
    @{ name = "home_375"; width = 375; height = 667; url = "http://localhost/Baymax_Voting_System/public/" },
    @{ name = "home_390"; width = 390; height = 844; url = "http://localhost/Baymax_Voting_System/public/" },
    @{ name = "home_430"; width = 430; height = 932; url = "http://localhost/Baymax_Voting_System/public/" },
    @{ name = "home_600"; width = 600; height = 900; url = "http://localhost/Baymax_Voting_System/public/" },
    @{ name = "home_768"; width = 768; height = 1024; url = "http://localhost/Baymax_Voting_System/public/" },
    @{ name = "home_1024"; width = 1024; height = 768; url = "http://localhost/Baymax_Voting_System/public/" },
    @{ name = "home_1280"; width = 1280; height = 800; url = "http://localhost/Baymax_Voting_System/public/" },
    @{ name = "home_1440"; width = 1440; height = 900; url = "http://localhost/Baymax_Voting_System/public/" },
    @{ name = "home_1920"; width = 1920; height = 1080; url = "http://localhost/Baymax_Voting_System/public/" },
    @{ name = "explore_1440"; width = 1440; height = 900; url = "http://localhost/Baymax_Voting_System/public/explore" },
    @{ name = "explore_390"; width = 390; height = 844; url = "http://localhost/Baymax_Voting_System/public/explore" },
    @{ name = "candidates_1440"; width = 1440; height = 900; url = "http://localhost/Baymax_Voting_System/public/candidates" },
    @{ name = "login_hub_1440"; width = 1440; height = 900; url = "http://localhost/Baymax_Voting_System/public/login" },
    @{ name = "login_admin_390"; width = 390; height = 844; url = "http://localhost/Baymax_Voting_System/public/login/admin" }
)

Write-Host "Capturing screenshots across viewports with absolute paths..."

foreach ($vp in $viewports) {
    $outFile = Join-Path $baseDir "$($vp.name).png"
    $url = $vp.url
    $w = $vp.width
    $h = $vp.height
    Start-Process -FilePath $chromePath -ArgumentList "--headless --disable-gpu --window-size=$w,$h --screenshot=`"$outFile`" $url" -Wait
    if (Test-Path $outFile) {
        $size = (Get-Item $outFile).Length
        Write-Host "Captured: $($vp.name).png ($w x $h, $size bytes)"
    } else {
        Write-Host "Failed: $($vp.name).png"
    }
}

Write-Host "Screenshot capture script complete."
