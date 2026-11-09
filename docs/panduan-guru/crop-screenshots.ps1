Add-Type -AssemblyName System.Drawing
$guideRoot = $PSScriptRoot
$guidePages = Get-Content -LiteralPath (Join-Path $guideRoot 'guide-pages.json') -Raw -Encoding UTF8 | ConvertFrom-Json
foreach ($guidePage in $guidePages) {
    foreach ($guideImage in $guidePage.images) {
        $guideName = $guideImage[0]
        $guideRect = $guideImage[2]
        $guideSource = [System.Drawing.Bitmap]::FromFile((Join-Path $guideRoot "assets/$guideName.jpg"))
        $guideCrop = New-Object System.Drawing.Rectangle([int]$guideRect[0], [int]$guideRect[1], [int]$guideRect[2], [int]$guideRect[3])
        $guideBitmap = $guideSource.Clone($guideCrop, [System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
        $guideBitmap.Save((Join-Path $guideRoot "assets/crop-$guideName.png"), [System.Drawing.Imaging.ImageFormat]::Png)
        $guideBitmap.Dispose()
        $guideSource.Dispose()
    }
}
Write-Output 'Screenshot dipotong sesuai area panduan.'
