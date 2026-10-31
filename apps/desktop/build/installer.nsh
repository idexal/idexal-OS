; idexal Os — advanced assisted installer.
;
; Flow (fresh install):
;   Welcome -> Licence -> Install for me/all users -> Folder
;           -> Installation options -> Progress -> Finish (launch)
;
; Flow (upgrade):
;   Welcome -> Upgrade summary -> Progress -> Finish (launch)
;
; The wizard is deliberately conservative: it never deletes a workspace, never
; changes a shortcut or auto-start choice the user made earlier, and refuses to
; start when the target disk is too small or the chosen folder is not writable.
; Every message is actionable. Silent installs (`/S`) use the same defaults and
; never wait on a dialog.

!include "nsDialogs.nsh"
!include "WinVer.nsh"
!include "LogicLib.nsh"
!include "FileFunc.nsh"

; Shared by the installer and the uninstaller, so it lives outside the
; BUILD_UNINSTALLER guard.
!define PP_RECEIPT_DIR "$APPDATA\com.idexal.os"
!define PP_RECEIPT_FILE "$APPDATA\com.idexal.os\install-receipt.ini"


!ifndef BUILD_UNINSTALLER

!ifdef APP_ARM64
  !define PP_ARCH "arm64"
!else
  !define PP_ARCH "x64"
!endif

; The app unpacked plus sidecars is well under 2 GB; leave room for updates,
; logs and Chromium's on-disk caches.
!define PP_MIN_FREE_GB 2
!define PP_MAX_PATH_LENGTH 180

Var ppOptionsDialog
Var ppDesktopShortcut
Var ppLaunchAtLogin
; Checkbox state survives even when the options page never ran (silent mode),
; so customInstall never reads a dialog handle it did not create.
Var ppDesktopShortcutState
Var ppLaunchAtLoginState
Var ppIsUpgrade
Var ppPreviousVersion

; ── Startup checks ─────────────────────────────────────────────────────────

Function ppCheckFreeSpace
  ${DriveSpace} "$INSTDIR" "/D=F /S=G" $0
  ${If} $0 == ""
    ; DriveSpace is best-effort; never block an install because a network
    ; drive hides its quota.
    DetailPrint "Could not read free space for $INSTDIR; skipping the disk check."
    Return
  ${EndIf}
  ${If} $0 < ${PP_MIN_FREE_GB}
    MessageBox MB_OK|MB_ICONSTOP|MB_SETFOREGROUND \
      "idexal Os needs at least ${PP_MIN_FREE_GB} GB of free space in $INSTDIR.$\r$\n$\r$\nFree some space and run setup again." /SD IDOK
    Quit
  ${EndIf}
FunctionEnd

Function ppCheckPathLength
  StrLen $0 "$INSTDIR"
  ${If} $0 > ${PP_MAX_PATH_LENGTH}
    MessageBox MB_OK|MB_ICONSTOP|MB_SETFOREGROUND \
      "This installation folder is too long for Windows:$\r$\n$INSTDIR$\r$\n$\r$\nChoose a shorter folder, for example C:\Program Files\idexal Os." /SD IDOK
    Quit
  ${EndIf}
FunctionEnd

Function ppReadPreviousVersion
  StrCpy $ppPreviousVersion ""
  ClearErrors
  ReadINIStr $0 "${PP_RECEIPT_FILE}" "install" "version"
  ${If} $0 != ""
    StrCpy $ppPreviousVersion $0
  ${Else}
    ReadINIStr $0 "$INSTDIR\install-receipt.ini" "install" "version"
    ${If} $0 != ""
      StrCpy $ppPreviousVersion $0
    ${EndIf}
  ${EndIf}
FunctionEnd

!macro customInit
  StrCpy $ppDesktopShortcutState 1
  StrCpy $ppLaunchAtLoginState 0
  StrCpy $ppIsUpgrade 0

  ${IfNot} ${AtLeastWin10}
    MessageBox MB_OK|MB_ICONSTOP|MB_SETFOREGROUND "idexal Os requires Windows 10 or later." /SD IDOK
    Quit
  ${EndIf}

  ; `customInit` runs after electron-builder has resolved the install folder
  ; and install scope, so these checks see the real target.
  IfFileExists "$INSTDIR\${APP_FILENAME}.exe" 0 ppFreshInstall
    StrCpy $ppIsUpgrade 1
  ppFreshInstall:
  Call ppReadPreviousVersion
  ${If} $ppPreviousVersion != ""
    StrCpy $ppIsUpgrade 1
  ${EndIf}

  Call ppCheckFreeSpace
  Call ppCheckPathLength
!macroend

; ── Welcome page ───────────────────────────────────────────────────────────

!macro customWelcomePage
  !ifndef MUI_WELCOMEPAGE_TITLE
    !define MUI_WELCOMEPAGE_TITLE "Welcome to idexal Os ${VERSION}"
  !endif
  !ifndef MUI_WELCOMEPAGE_TEXT
    !define MUI_WELCOMEPAGE_TEXT "This wizard installs idexal Os on this computer.$\r$\n$\r$\nidexal Os is a local-first AI workspace for your own files. It opens with an account: on first launch the app asks you to sign in, or to start a subscription and choose a plan, and it opens as soon as either is done.$\r$\n$\r$\nSetup checks the target folder and free space, preserves your workspaces on upgrades, and launches the app when it finishes."
  !endif
  !insertmacro MUI_PAGE_WELCOME
!macroend

; ── Installation options / upgrade summary ─────────────────────────────────

Function ppOptionsPageCreate
  nsDialogs::Create 1018
  Pop $ppOptionsDialog
  ${If} $ppOptionsDialog == error
    Abort
  ${EndIf}

  GetDlgItem $0 $HWNDPARENT 1037
  ${If} $ppIsUpgrade == 1
    SendMessage $0 ${WM_SETTEXT} 0 "STR:Upgrade idexal Os"
  ${Else}
    SendMessage $0 ${WM_SETTEXT} 0 "STR:Installation options"
  ${EndIf}
  GetDlgItem $0 $HWNDPARENT 1038
  ${If} $ppIsUpgrade == 1
    SendMessage $0 ${WM_SETTEXT} 0 "STR:Your files and settings stay in place."
  ${Else}
    SendMessage $0 ${WM_SETTEXT} 0 "STR:Choose how idexal Os starts on this computer."
  ${EndIf}

  ${If} $ppIsUpgrade == 1
    ${If} $ppPreviousVersion != ""
      ${NSD_CreateLabel} 0 0 100% 30u "Setup is upgrading idexal Os from $ppPreviousVersion to ${VERSION} in:$\r$\n$INSTDIR$\r$\n$\r$\nYour workspaces, settings and sign-in stay on this computer."
    ${Else}
      ${NSD_CreateLabel} 0 0 100% 30u "Setup is upgrading idexal Os in:$\r$\n$INSTDIR$\r$\n$\r$\nYour workspaces, settings and sign-in stay on this computer."
    ${EndIf}
    Pop $0
    ${NSD_CreateLabel} 0 48u 100% 24u "Setup keeps the shortcut and auto-start choices you made earlier. It also verifies the installed app and its local engine before finishing."
    Pop $0
  ${Else}
    ${NSD_CreateLabel} 0 0 100% 26u "Setup is ready to install idexal Os in:$\r$\n$INSTDIR"
    Pop $0

    ${NSD_CreateCheckBox} 0 38u 100% 12u "Create a desktop shortcut"
    Pop $ppDesktopShortcut
    ${NSD_SetState} $ppDesktopShortcut ${BST_CHECKED}

    ${NSD_CreateCheckBox} 0 56u 100% 12u "Start idexal Os when I sign in to Windows"
    Pop $ppLaunchAtLogin
    ${NSD_SetState} $ppLaunchAtLogin ${BST_UNCHECKED}

    ${NSD_CreateLabel} 0 82u 100% 30u "You can change these later. The app stores its data locally in your user profile; uninstalling the app does not delete your workspaces."
    Pop $0
  ${EndIf}

  nsDialogs::Show
FunctionEnd

Function ppOptionsPageLeave
  ${If} $ppIsUpgrade == 1
    Return
  ${EndIf}

  ${NSD_GetState} $ppDesktopShortcut $0
  StrCpy $ppDesktopShortcutState $0
  ${NSD_GetState} $ppLaunchAtLogin $0
  StrCpy $ppLaunchAtLoginState $0

  ; The directory page runs after customInit, so re-check the path the member
  ; actually chose. electron-builder appends the app folder after this page.
  StrLen $0 "$INSTDIR"
  ${If} $0 > ${PP_MAX_PATH_LENGTH}
    MessageBox MB_OK|MB_ICONSTOP|MB_SETFOREGROUND \
      "This installation folder is too long for Windows:$\r$\n$INSTDIR$\r$\n$\r$\nChoose a shorter folder, for example C:\Program Files\idexal Os." /SD IDOK
    Abort
  ${EndIf}

  ; A fresh install must prove it can write before the progress page starts.
  ClearErrors
  CreateDirectory "$INSTDIR"
  FileOpen $0 "$INSTDIR\.idexal-write-test" w
  ${If} $0 == ""
    MessageBox MB_OK|MB_ICONSTOP|MB_SETFOREGROUND "This folder is not writable:$\r$\n$INSTDIR$\r$\n$\r$\nChoose another folder, or run setup as an administrator."
    Abort
  ${EndIf}
  FileWrite $0 "idexal-os-installer"
  FileClose $0
  Delete "$INSTDIR\.idexal-write-test"

  ${DriveSpace} "$INSTDIR" "/D=F /S=G" $0
  ${If} $0 != ""
  ${AndIf} $0 < ${PP_MIN_FREE_GB}
    MessageBox MB_OK|MB_ICONSTOP|MB_SETFOREGROUND "idexal Os needs at least ${PP_MIN_FREE_GB} GB of free space in $INSTDIR."
    Abort
  ${EndIf}
FunctionEnd

!macro customPageAfterChangeDir
  Page custom ppOptionsPageCreate ppOptionsPageLeave
!macroend

; ── Install completion ─────────────────────────────────────────────────────

!macro customInstall
  ; Fresh installs honour the two optional choices. Upgrades keep the prior
  ; state, so an unattended update cannot silently change a user's workflow.
  ${If} $ppIsUpgrade == 0
    ${If} $ppDesktopShortcutState != ${BST_CHECKED}
      ${If} $newDesktopLink != ""
        Delete "$newDesktopLink"
      ${EndIf}
    ${EndIf}

    SetShellVarContext current
    ${If} $ppLaunchAtLoginState == ${BST_CHECKED}
      CreateDirectory "$SMSTARTUP"
      CreateShortCut "$SMSTARTUP\idexal Os.lnk" "$INSTDIR\${APP_FILENAME}.exe" "" "$INSTDIR\${APP_FILENAME}.exe" 0 SW_SHOWNORMAL
    ${Else}
      Delete "$SMSTARTUP\idexal Os.lnk"
    ${EndIf}
  ${EndIf}

  ; Health gate: the app and its local engine metadata must be present before
  ; the finish page offers to launch. A broken extraction is reported here,
  ; not as a mystery startup failure.
  IfFileExists "$INSTDIR\${APP_FILENAME}.exe" 0 ppHealthFailed
  IfFileExists "$INSTDIR\resources\sidecars\versions.json" 0 ppHealthFailed
  Goto ppHealthPassed

  ppHealthFailed:
    DetailPrint "Installation health check failed for $INSTDIR."
    MessageBox MB_OK|MB_ICONEXCLAMATION|MB_SETFOREGROUND \
      "idexal Os was installed, but setup could not verify the app files in:$\r$\n$INSTDIR$\r$\n$\r$\nRun setup again. If the problem continues, install to a different folder." /SD IDOK
  ppHealthPassed:

  ; A machine-readable receipt for support and the app's About/diagnostics
  ; view. It contains no secrets and no user name.
  CreateDirectory "${PP_RECEIPT_DIR}"
  WriteINIStr "${PP_RECEIPT_FILE}" "install" "version" "${VERSION}"
  WriteINIStr "${PP_RECEIPT_FILE}" "install" "previous_version" "$ppPreviousVersion"
  WriteINIStr "${PP_RECEIPT_FILE}" "install" "architecture" "${PP_ARCH}"
  WriteINIStr "${PP_RECEIPT_FILE}" "install" "scope" "$installMode"
  WriteINIStr "${PP_RECEIPT_FILE}" "install" "install_dir" "$INSTDIR"
  WriteINIStr "${PP_RECEIPT_FILE}" "install" "wizard" "advanced-v2"
  WriteINIStr "$INSTDIR\install-receipt.ini" "install" "version" "${VERSION}"
  WriteINIStr "$INSTDIR\install-receipt.ini" "install" "architecture" "${PP_ARCH}"
  WriteINIStr "$INSTDIR\install-receipt.ini" "install" "scope" "$installMode"
!macroend

!endif ; BUILD_UNINSTALLER

; ── Uninstaller ────────────────────────────────────────────────────────────
; The generated uninstaller runs quietly by design. It only removes the
; application, its startup shortcut and its install receipt; workspaces, app
; settings and sign-in data are never touched.

!macro customUnInstall
  SetShellVarContext current
  Delete "$SMSTARTUP\idexal Os.lnk"
  Delete "${PP_RECEIPT_FILE}"
  Delete "$INSTDIR\install-receipt.ini"

  ; Legacy brand shortcut left by an older install.
  StrCpy $1 ""
  ClearErrors
  FileOpen $0 "$APPDATA\com.idexal.os\windows-brand-shortcut.txt" r
  IfErrors ppUninstallBrandDone
    FileRead $0 $1
    FileClose $0
    ${If} $1 != ""
      Delete "$1"
    ${EndIf}
  ppUninstallBrandDone:
  Delete "$APPDATA\com.idexal.os\windows-brand-shortcut.txt"

!macroend
