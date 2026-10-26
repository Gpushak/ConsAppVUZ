<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Models\Slot;
use App\Support\Building;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $format = (string) $this->input('format');
        $online = $format === 'online';
        $comment = trim((string) $this->input('comment'));
        $url = trim((string) $this->input('meeting_url'));

        $this->merge([
            'format' => $format,
            'building' => $online ? null : $this->input('building'),
            'room' => $online ? null : trim((string) $this->input('room')),
            'comment' => $comment === '' ? null : $comment,
            'meeting_url' => $url === '' ? null : $url,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $online = $this->input('format') === 'online';

        return [
            'date' => ['required', 'regex:/^(?:\d{2}\.\d{2}\.\d{4}|\d{4}-\d{2}-\d{2})$/'],
            'start_time' => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'end_time' => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'format' => ['required', Rule::in(['in_person', 'online'])],
            'building' => $online
                ? ['nullable']
                : ['required', 'string', Rule::in(Building::LIST)],
            'room' => $online
                ? ['nullable']
                : ['required', 'string', 'max:32'],
            'comment' => ['nullable', 'string', 'max:500'],
            'meeting_url' => $online
                ? ['required', 'string', 'max:500', 'regex:/^https?:\/\/\S+$/i']
                : ['nullable', 'string', 'max:500', 'regex:/^https?:\/\/\S+$/i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Укажите дату.',
            'date.regex' => 'Укажите дату в формате ДД.ММ.ГГГГ.',
            'start_time.required' => 'Укажите начало.',
            'start_time.regex' => 'Укажите начало в формате ЧЧ:ММ.',
            'end_time.required' => 'Укажите конец.',
            'end_time.regex' => 'Укажите конец в формате ЧЧ:ММ.',
            'format.required' => 'Выберите формат: очно или онлайн.',
            'format.in' => 'Выберите формат: очно или онлайн.',
            'building.required' => 'Выберите корпус.',
            'building.in' => 'Корпус выбирается из списка.',
            'room.required' => 'Укажите аудиторию.',
            'comment.max' => 'Комментарий не длиннее 500 символов.',
            'meeting_url.required' => 'Для онлайн-слота укажите ссылку.',
            'meeting_url.regex' => 'Ссылка должна начинаться с http:// или https://.',
            'meeting_url.max' => 'Ссылка не длиннее 500 символов.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $value = (string) $this->input('date');
            if (str_contains($value, '.')) {
                $day = Carbon::createFromFormat('!d.m.Y', $value);
                if ($day === false || $day->format('d.m.Y') !== $value) {
                    $validator->errors()->add('date', 'Укажите дату в формате ДД.ММ.ГГГГ.');

                    return;
                }
            }

            [$starts, $ends] = $this->consultationWindow();

            if ($ends->getTimestamp() - $starts->getTimestamp() < 15 * 60) {
                $validator->errors()->add('end_time', 'Конец позже начала, длительность не короче 15 минут.');

                return;
            }

            if ($starts->lessThanOrEqualTo(now())) {
                $validator->errors()->add('date', 'Начало должно быть в будущем.');

                return;
            }

            $teacherId = $this->user()->teacherProfile->id;
            $overlap = Slot::query()
                ->where('teacher_profile_id', $teacherId)
                ->where('starts_at', '<', $ends)
                ->where('ends_at', '>', $starts)
                ->where(function ($query) {
                    $query->where(function ($query) {
                        $query->whereNull('conducted_at')->where('is_published', true);
                    })->orWhereHas('bookings', function ($bookings) {
                        $bookings->where('status', Booking::BOOKED);
                    });
                })
                ->exists();

            if ($overlap) {
                $validator->errors()->add('start_time', 'Это время пересекается с другим вашим слотом.');
            }
        });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function consultationWindow(): array
    {
        $value = (string) $this->input('date');
        $day = str_contains($value, '.')
            ? Carbon::createFromFormat('!d.m.Y', $value)
            : Carbon::parse($value)->startOfDay();

        $start = substr((string) $this->input('start_time'), 0, 5);
        $end = substr((string) $this->input('end_time'), 0, 5);

        return [
            Carbon::parse($day->toDateString().' '.$start),
            Carbon::parse($day->toDateString().' '.$end),
        ];
    }

    public function slotAttributes(): array
    {
        [$starts, $ends] = $this->consultationWindow();
        $online = $this->input('format') === 'online';

        return [
            'starts_at' => $starts,
            'ends_at' => $ends,
            'building' => $online ? null : $this->input('building'),
            'room' => $online ? null : $this->input('room'),
            'comment' => $this->input('comment'),
            'meeting_url' => $this->input('meeting_url'),
            'is_published' => true,
        ];
    }
}
