$transcriptPath = "c:/Users/A_Fru/AppData/Roaming/Code/User/workspaceStorage/42710f470add604a552155271d925c0e/GitHub.copilot-chat/transcripts/506dcc21-96a3-4786-932c-f95d34a0073b.jsonl"
$cutoff = (Get-Date).AddYears(-100)

function Normalize-Newlines([string]$text) {
    if ($null -eq $text) { return "" }
    return ($text -replace "`r`n", "`n") -replace "`r", "`n"
}

function Split-PatchFileSections([string[]]$lines) {
    $sections = @()
    $i = 0
    while ($i -lt $lines.Count) {
        $line = $lines[$i]
        if ($line.StartsWith('*** Update File: ') -or $line.StartsWith('*** Add File: ') -or $line.StartsWith('*** Delete File: ')) {
            if ($line.StartsWith('*** Update File: ')) {
                $action = 'Update'
                $path = $line.Substring('*** Update File: '.Length)
            } elseif ($line.StartsWith('*** Add File: ')) {
                $action = 'Add'
                $path = $line.Substring('*** Add File: '.Length)
            } else {
                $action = 'Delete'
                $path = $line.Substring('*** Delete File: '.Length)
            }

            $i++
            $body = @()
            while ($i -lt $lines.Count) {
                $peek = $lines[$i]
                if ($peek.StartsWith('*** Update File: ') -or $peek.StartsWith('*** Add File: ') -or $peek.StartsWith('*** Delete File: ') -or $peek -eq '*** End Patch') {
                    break
                }
                $body += $peek
                $i++
            }

            $sections += [pscustomobject]@{ Action = $action; Path = $path; Body = $body }
            continue
        }
        $i++
    }
    return $sections
}

function Split-Hunks([string[]]$bodyLines) {
    $hunks = @()
    $current = @()
    foreach ($line in $bodyLines) {
        if ($line.StartsWith('@@')) {
            if ($current.Count -gt 0) {
                $hunks += ,$current
                $current = @()
            }
            continue
        }
        $current += $line
    }
    if ($current.Count -gt 0) {
        $hunks += ,$current
    }
    return $hunks
}

function Apply-UpdateSection($path, [string[]]$bodyLines) {
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Update target missing: $path"
    }

    $raw = Get-Content -LiteralPath $path -Raw
    $useCrLf = $raw.Contains("`r`n")
    $content = Normalize-Newlines $raw

    $hunks = Split-Hunks $bodyLines
    foreach ($hunk in $hunks) {
        $hasChange = $false
        foreach ($line in $hunk) {
            if ($line.StartsWith('+') -or $line.StartsWith('-')) { $hasChange = $true; break }
        }
        if (-not $hasChange) { continue }

        $oldLines = @()
        $newLines = @()

        foreach ($line in $hunk) {
            if ($line.StartsWith('+')) {
                $newLines += $line.Substring(1)
            } elseif ($line.StartsWith('-')) {
                $oldLines += $line.Substring(1)
            } else {
                $oldLines += $line
                $newLines += $line
            }
        }

        $oldBlock = ($oldLines -join "`n")
        $newBlock = ($newLines -join "`n")

        if ($oldBlock -eq $newBlock) { continue }

        $idx = $content.IndexOf($oldBlock)
        if ($idx -lt 0) {
            throw "Hunk not found in $path"
        }

        $content = $content.Substring(0, $idx) + $newBlock + $content.Substring($idx + $oldBlock.Length)
    }

    if ($useCrLf) {
        $content = $content -replace "`n", "`r`n"
    }

    Set-Content -LiteralPath $path -Value $content -NoNewline
}

function Apply-AddSection($path, [string[]]$bodyLines) {
    $newLines = @()
    foreach ($line in $bodyLines) {
        if ($line.StartsWith('+')) {
            $newLines += $line.Substring(1)
        } elseif ($line.StartsWith('@@')) {
            continue
        }
    }
    $content = ($newLines -join "`n")
    $dir = Split-Path -Parent $path
    if ($dir -and -not (Test-Path -LiteralPath $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
    Set-Content -LiteralPath $path -Value $content -NoNewline
}

function Apply-DeleteSection($path) {
    if (Test-Path -LiteralPath $path) {
        Remove-Item -LiteralPath $path -Force
    }
}

$rawLines = Get-Content -LiteralPath $transcriptPath
$records = @()
foreach ($line in $rawLines) {
    try {
        $obj = $line | ConvertFrom-Json -Depth 100
    } catch {
        continue
    }

    if ($obj.type -ne 'tool.execution_start') { continue }
    if ($obj.data.toolName -ne 'apply_patch') { continue }

    try {
        $ts = [datetime]$obj.timestamp
    } catch {
        continue
    }

    if ($ts -lt $cutoff) { continue }
    $input = $obj.data.arguments.input
    if ([string]::IsNullOrWhiteSpace($input)) { continue }

    $records += [pscustomobject]@{
        Timestamp = $ts
        Input = $input
    }
}

$records = $records | Sort-Object Timestamp

if ($records.Count -eq 0) {
    Write-Output "No apply_patch records found in the last 4 hours."
    exit 0
}

$applied = 0
$failed = 0
$failures = @()

foreach ($rec in $records) {
    $patchText = Normalize-Newlines $rec.Input
    $lines = $patchText -split "`n"
    $sections = Split-PatchFileSections $lines

    try {
        foreach ($section in $sections) {
            switch ($section.Action) {
                'Update' { Apply-UpdateSection $section.Path $section.Body }
                'Add'    { Apply-AddSection $section.Path $section.Body }
                'Delete' { Apply-DeleteSection $section.Path }
            }
        }
        $applied++
    } catch {
        $failed++
        $failures += "[$($rec.Timestamp.ToString('o'))] $($_.Exception.Message)"
    }
}

Write-Output "Patches discovered: $($records.Count)"
Write-Output "Patches applied:    $applied"
Write-Output "Patches failed:     $failed"
if ($failures.Count -gt 0) {
    Write-Output "Failures:"
    $failures | ForEach-Object { Write-Output $_ }
    exit 1
}

