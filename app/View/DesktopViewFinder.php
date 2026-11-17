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
            $layout = DesktopWindow::rosterWindow() ? 'layouts.desktop-roster' : 'layouts.desktop';

            return parent::find($layout);
        }

        return parent::find($view);
    }
}
