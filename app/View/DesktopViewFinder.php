<?php

namespace App\View;

use App\Support\DesktopWindow;
use Illuminate\View\FileViewFinder;

class DesktopViewFinder extends FileViewFinder
{
    /**
     * The diploma layout stays layouts.app. The Electron flag resolves the sidebar frame instead.
     */
    public function find($view)
    {
        if ($view === 'layouts.app' && DesktopWindow::requested()) {
            return parent::find('layouts.desktop');
        }

        return parent::find($view);
    }
}
