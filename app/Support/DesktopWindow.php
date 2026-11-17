<?php

namespace App\Support;

use Illuminate\Http\Request;

final class DesktopWindow
{
    public const HEADER = 'X-Desktop-Window';

    public const FRAGMENT = 'X-Desktop-Fragment';

    public const USER_AGENT = 'ConsAppDesktop/1';

    /**
     * Confirmation pages that stay on these routes and open as dialogs in the window.
     *
     * @var list<string>
     */
    public const DIALOG_ROUTES = [
        'bookings.create',
        'bookings.cancel',
        'teacher.bookings.cancel',
        'teacher.slots.create',
        'teacher.slots.unpublish.confirm',
        'teacher.slots.conduct',
        'teacher.slots.series.stop',
        'admin.users.block.confirm',
        'admin.users.unblock.confirm',
        'admin.users.password.confirm',
    ];

    public static function requested(?Request $request = null): bool
    {
        if ($request === null) {
            if (! app()->bound('request')) {
                return false;
            }

            $request = request();
        }

        if (! $request instanceof Request) {
            return false;
        }

        return $request->headers->get(self::HEADER) === '1'
            || str_contains((string) $request->userAgent(), self::USER_AGENT);
    }
}
