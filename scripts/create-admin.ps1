param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9._-]{1,80}$')]
    [string] $Username,
    [string] $PhpPath = 'C:\xampp\php\php.exe'
)

if (-not (Test-Path -LiteralPath $PhpPath)) {
    throw "PHP was not found at '$PhpPath'. Set -PhpPath to your XAMPP php.exe path."
}

$securePassword = Read-Host 'Enter a password with 12 to 72 characters' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    $plainPassword | & $PhpPath (Join-Path $PSScriptRoot 'create-admin.php') $Username
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}
finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    $plainPassword = $null
    $securePassword.Dispose()
}
