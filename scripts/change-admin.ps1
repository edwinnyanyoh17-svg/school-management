param(
    [string] $CurrentUsername = 'admin',
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9._-]{1,80}$')]
    [string] $NewUsername,
    [string] $PhpPath = 'C:\xampp\php\php.exe'
)

if ($CurrentUsername -notmatch '^[A-Za-z0-9._-]{1,80}$') {
    throw 'CurrentUsername may contain letters, digits, dot, underscore, and hyphen (up to 80 characters).'
}
if (-not (Test-Path -LiteralPath $PhpPath)) {
    throw "PHP was not found at '$PhpPath'. Set -PhpPath to your XAMPP php.exe path."
}

$securePassword = Read-Host 'Enter a new password with 12 to 72 characters' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    $plainPassword | & $PhpPath (Join-Path $PSScriptRoot 'change-admin.php') $CurrentUsername $NewUsername
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}
finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    $plainPassword = $null
    $securePassword.Dispose()
}
