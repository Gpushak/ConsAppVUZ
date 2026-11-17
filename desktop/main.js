const { app, BrowserWindow, Menu, ipcMain, session, shell } = require('electron');
const path = require('path');

const AGENT = 'ConsAppDesktop/1';

function baseUrl() {
  return (process.env.CONSAPP_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
}

function sameOrigin(url) {
  try {
    return new URL(url).origin === new URL(baseUrl()).origin;
  } catch (error) {
    return false;
  }
}

function withDesktopAgent(contents) {
  const current = contents.getUserAgent() || 'Mozilla/5.0';
  if (!current.includes(AGENT)) {
    contents.setUserAgent(current + ' ' + AGENT);
  }
}

function attachWindowFlag() {
  session.defaultSession.webRequest.onBeforeSendHeaders({ urls: ['http://*/*', 'https://*/*'] }, (details, callback) => {
    details.requestHeaders['X-Desktop-Window'] = '1';
    if (rosterContents.has(details.webContentsId)) {
      details.requestHeaders['X-Desktop-Roster'] = '1';
    }
    callback({ requestHeaders: details.requestHeaders });
  });
}

function lockNavigation(win) {
  win.webContents.setWindowOpenHandler(({ url }) => {
    if (!sameOrigin(url)) {
      shell.openExternal(url);
    }
    return { action: 'deny' };
  });

  win.webContents.on('will-navigate', (event, url) => {
    if (!sameOrigin(url)) {
      event.preventDefault();
      shell.openExternal(url);
    }
  });
}

let mainWindow = null;
let role = '';
const rosterWindows = new Map();
const rosterContents = new Set();

function send(channel, ...args) {
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.webContents.send(channel, ...args);
  }
}

function bookingLabel() {
  if (role === 'teacher') {
    return 'Новый слот';
  }
  if (role === 'admin') {
    return 'Новый преподаватель';
  }

  return 'Записаться';
}

function buildMenu() {
  const signedIn = role === 'student' || role === 'teacher' || role === 'admin';
  const template = [
    {
      label: 'Файл',
      submenu: [
        { label: 'Выйти', enabled: signedIn, click: () => send('shell-logout') },
      ],
    },
    {
      label: 'Запись',
      submenu: [
        {
          label: bookingLabel(),
          accelerator: 'CmdOrCtrl+N',
          enabled: signedIn,
          click: () => send('shell-role-action'),
        },
      ],
    },
    {
      label: 'Вид',
      submenu: [
        { label: 'Предыдущая неделя', accelerator: 'CmdOrCtrl+Left', click: () => send('shell-week', 'prev') },
        { label: 'Следующая неделя', accelerator: 'CmdOrCtrl+Right', click: () => send('shell-week', 'next') },
      ],
    },
    {
      label: 'Окно',
      submenu: [
        { label: 'Список записавшихся', click: () => send('shell-roster') },
      ],
    },
  ];

  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

function windowOptions(extra) {
  return Object.assign({
    width: 1280,
    height: 860,
    minWidth: 960,
    minHeight: 640,
    backgroundColor: '#f2f4f6',
    autoHideMenuBar: false,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  }, extra);
}

function showOffline(win) {
  const url = baseUrl();
  const html = `<!DOCTYPE html><html lang="ru"><meta charset="utf-8"><title>Запись на консультацию</title>
<body style="font-family:system-ui,sans-serif;background:#f2f4f6;color:#1b2128;padding:48px">
<h1>Laravel не запущен</h1>
<p>Окно открывает уже работающий сайт. Запустите <code>php artisan serve</code>, затем обновите окно.</p>
<p>Адрес: ${url}</p>
</body></html>`;
  win.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(html));
}

function createMain() {
  mainWindow = new BrowserWindow(windowOptions({
    width: 1400,
    height: 900,
    title: 'Запись на консультацию',
  }));
  withDesktopAgent(mainWindow.webContents);
  lockNavigation(mainWindow);
  mainWindow.webContents.on('did-fail-load', (event, code, description, url, isMainFrame) => {
    if (!isMainFrame || code === -3 || String(url).startsWith('data:')) {
      return;
    }
    showOffline(mainWindow);
  });
  mainWindow.loadURL(baseUrl() + '/');
  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

function rosterSlotId(url) {
  try {
    const match = new URL(url).pathname.match(/\/slots\/(\d+)\/roster\/?$/);
    return match ? match[1] : '';
  } catch (error) {
    return '';
  }
}

function rosterStay(url, slotId) {
  if (!slotId || !sameOrigin(url)) {
    return false;
  }
  let path;
  try {
    path = new URL(url).pathname.replace(/\/$/, '') || '/';
  } catch (error) {
    return false;
  }
  if (path === '/slots/' + slotId + '/roster') {
    return true;
  }

  return /^\/teacher\/bookings\/\d+\/cancel$/.test(path);
}

function lockRoster(win, rosterUrl) {
  const slotId = rosterSlotId(rosterUrl);
  const guard = (event, url) => {
    if (!sameOrigin(url)) {
      event.preventDefault();
      shell.openExternal(url);
      return;
    }
    if (!rosterStay(url, slotId)) {
      event.preventDefault();
    }
  };

  win.webContents.setWindowOpenHandler(({ url }) => {
    if (!sameOrigin(url)) {
      shell.openExternal(url);
    }
    return { action: 'deny' };
  });
  win.webContents.on('will-navigate', guard);
  win.webContents.on('will-redirect', guard);
}

function openRoster(url) {
  let absolute;
  try {
    absolute = new URL(url, baseUrl() + '/').href;
  } catch (error) {
    return;
  }
  if (!sameOrigin(absolute)) {
    return;
  }
  const existing = rosterWindows.get(absolute);
  if (existing && !existing.isDestroyed()) {
    existing.focus();
    return;
  }
  const child = new BrowserWindow(windowOptions({
    width: 720,
    height: 640,
    minWidth: 480,
    minHeight: 360,
    parent: mainWindow || undefined,
    title: 'Записавшиеся',
    autoHideMenuBar: true,
  }));
  child.removeMenu();
  const contentsId = child.webContents.id;
  rosterContents.add(contentsId);
  withDesktopAgent(child.webContents);
  lockRoster(child, absolute);
  rosterWindows.set(absolute, child);
  child.on('closed', () => {
    rosterContents.delete(contentsId);
    rosterWindows.delete(absolute);
  });
  child.loadURL(absolute);
}

ipcMain.on('roster-open', (event, url) => {
  if (typeof url === 'string') {
    openRoster(url);
  }
});

ipcMain.on('shell-role', (event, next) => {
  role = next === 'student' || next === 'teacher' || next === 'admin' ? next : '';
  buildMenu();
});

app.whenReady().then(() => {
  attachWindowFlag();
  buildMenu();
  createMain();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createMain();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
