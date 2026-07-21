<?php

namespace App\Notifications;

use App\Models\AttendanceRecord;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentAbsenceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $attendanceRecordId)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filter_var(Setting::getValue('absence_email_enabled', false), FILTER_VALIDATE_BOOL)
            && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'mail' => (string) config('queue.default', 'database'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['mail' => 'notifications'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'student-absence';
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $record = $this->record();

        return [
            'attendance_record_id' => $record->id,
            'student_id' => $record->student_id,
            'title' => 'Attendance alert for '.$record->student->user->fullName(),
            'body' => $record->student->user->fullName().' was marked absent on '.$record->attendance_date->format('d M Y').'.',
            'category' => 'Attendance',
            'priority' => 'high',
            'url' => route('app.portal.index', ['student_id' => $record->student_id]),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $record = $this->record();
        $studentName = $record->student->user->fullName();

        return (new MailMessage)
            ->subject('Attendance alert for '.$studentName)
            ->greeting('Hello '.$this->recipientName($notifiable).',')
            ->line($studentName.' was marked absent on '.$record->attendance_date->format('d M Y').'.')
            ->lineIf(filled($record->note), 'School note: '.$record->note)
            ->action('Open Parent Portal', route('app.portal.index', ['student_id' => $record->student_id]))
            ->line('Please contact the school office if this record needs clarification.');
    }

    private function record(): AttendanceRecord
    {
        return AttendanceRecord::query()
            ->with(['student.user'])
            ->findOrFail($this->attendanceRecordId);
    }

    private function recipientName(object $notifiable): string
    {
        return $notifiable instanceof User ? $notifiable->fullName() : 'Parent';
    }
}
