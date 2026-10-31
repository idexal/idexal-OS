# Deploy to GitHub - Two Repositories Setup

## 1. Private Repository (Source Code)
**Repository Name:** `idexalOS-Full-SourceCode`  
**Visibility:** Private  
**Purpose:** Complete source code backup

## 2. Public Repository (Production Releases)
**Repository Name:** `idexal-OS`  
**Visibility:** Public  
**Purpose:** Production releases, documentation, downloads

---

## Step 1: Initialize Private Repository

```bash
# Clone or create private repo
git remote add origin-private https://github.com/idexal/idexalOS-Full-SourceCode.git

# Add all changes
git add .

# Commit with descriptive message
git commit -m "feat: complete implementation of real-time sync, hybrid models, marketplace, and Western numeral formatting"

# Push to private repo
git push origin-private main --force
```

## Step 2: Create Public Release Repository

```bash
# Clone fresh public repo OR link as secondary remote
git remote add origin-public https://github.com/idexal/idexal-OS.git

# Create release artifacts structure
mkdir -p releases/
```

## Step 3: Generate Release Artifacts

### Version Number Format
Use semantic versioning: `v1.X.Y` where X is major updates, Y is minor patches

### Release Files to Generate:
```bash
# macOS
idexal-OS-v1.X.X-mac.zip
idexal-OS-v1.X.X.dmg

# Windows
idexal-OS-Setup-v1.X.X.exe

# Linux
idexal-os-server-linux-x64-v1.X.X.tar.gz

# Auto-update manifests
latest.yml
latest-mac.yml
```

## Step 4: Upload to Public Repository

```bash
# Tag the release
git tag -a v1.X.X -m "Release v1.X.X - Complete sync system, hybrid models, marketplace"

# Push tags to public repo
git push origin-public v1.X.X

# Create GitHub release with all assets
gh release create v1.X.X \
  releases/idexal-OS-v1.X.X-mac.zip \
  releases/idexal-OS-v1.X.X.dmg \
  releases/idexal-OS-Setup-v1.X.X.exe \
  releases/idexal-os-server-linux-x64-v1.X.X.tar.gz \
  releases/latest.yml \
  releases/latest-mac.yml \
  --title "idexal OS v1.X.X" \
  --notes "Complete release with real-time synchronization, hybrid models, plugin marketplace, and Western numeral formatting"
```

---

## File Structure for Releases

```
releases/
├── idexal-OS-v1.X.X-mac.zip          # macOS portable
├── idexal-OS-v1.X.X.dmg              # macOS installer
├── idexal-OS-Setup-v1.X.X.exe        # Windows installer
├── idexal-os-server-linux-x64-v1.X.X.tar.gz  # Linux server
├── latest.yml                        # Windows auto-update manifest
└── latest-mac.yml                    # macOS auto-update manifest
```

---

## Notes:
- Private repo has FULL source code (complete development history)
- Public repo has ONLY production-ready stable releases
- Use Git LFS for large binary files (>100MB)
- Enable branch protection on public repo main branch
- Use CODEOWNERS file to specify project maintainers
- Include CHANGELOG.md in every release
- Sign releases with GPG key for authenticity verification
