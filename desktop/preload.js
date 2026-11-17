const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('desktopShell', {
  openRoster(url) {
    ipcRenderer.send('roster-open', url);
  },
  setRole(role) {
    ipcRenderer.send('shell-role', role);
  },
  onWeek(handler) {
    ipcRenderer.on('shell-week', (event, direction) => handler(direction));
  },
  onRoleAction(handler) {
    ipcRenderer.on('shell-role-action', () => handler());
  },
  onLogout(handler) {
    ipcRenderer.on('shell-logout', () => handler());
  },
  onRoster(handler) {
    ipcRenderer.on('shell-roster', () => handler());
  },
});
