<?php

namespace App\Services;

use App\Models\Call;
use App\Models\Event;
use App\Models\EmailActivity;
use App\Models\Meeting;
use App\Models\MessageConversation;
use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripTicket;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ActivityTypeRegistry
{
    public const TASK = 'TASK';
    public const EVENT = 'EVENT';
    public const CALL = 'CALL';
    public const MEETING = 'MEETING';
    public const EMAIL = 'EMAIL';
    public const MESSAGE = 'MESSAGE';
    public const OFFICIAL_BUSINESS_TRAVEL = 'OFFICIAL_BUSINESS_TRAVEL';
    public const OFFICIAL_BUSINESS_TRIP_TICKET = 'OFFICIAL_BUSINESS_TRIP_TICKET';

    public function __construct(
        private readonly OfficialBusinessActivityService $officialBusinessActivities
    ) {}

    public function supportedTypes(): array
    {
        return [
            self::TASK,
            self::EVENT,
            self::CALL,
            self::MEETING,
            self::EMAIL,
            self::MESSAGE,
            self::OFFICIAL_BUSINESS_TRAVEL,
            self::OFFICIAL_BUSINESS_TRIP_TICKET,
        ];
    }

    public function normalize(string $type): string
    {
        $normalized = strtoupper(str_replace(['-', ' '], '_', trim($type)));

        if ($normalized === 'OFFICIAL_BUSINESS') {
            $normalized = self::OFFICIAL_BUSINESS_TRAVEL;
        }

        if (! in_array($normalized, $this->supportedTypes(), true)) {
            throw ValidationException::withMessages([
                'activity_type' => 'Unsupported activity type.',
            ]);
        }

        return $normalized;
    }

    public function modelClass(string $type): ?string
    {
        return match ($this->normalize($type)) {
            self::TASK => Task::class,
            self::EVENT => Event::class,
            self::CALL => Call::class,
            self::MEETING => Meeting::class,
            self::EMAIL => EmailActivity::class,
            self::MESSAGE => MessageConversation::class,
            self::OFFICIAL_BUSINESS_TRAVEL => OfficialBusinessTrip::class,
            self::OFFICIAL_BUSINESS_TRIP_TICKET => OfficialBusinessTripTicket::class,
        };
    }

    public function resolve(string $type, int $id, User $user): Model
    {
        $type = $this->normalize($type);

        if ($type === self::EMAIL) {
            return EmailActivity::query()->visibleTo($user)->findOrFail($id);
        }

        if ($type === self::MESSAGE) {
            return MessageConversation::query()->visibleTo($user)->findOrFail($id);
        }

        if ($type === self::OFFICIAL_BUSINESS_TRAVEL) {
            $trip = $this->officialBusinessActivities
                ->context($user)['records']
                ->firstWhere('id', $id);

            abort_unless($trip, 404);

            return $trip;
        }

        if ($type === self::OFFICIAL_BUSINESS_TRIP_TICKET) {
            $ticket = OfficialBusinessTripTicket::query()->findOrFail($id);
            \Illuminate\Support\Facades\Gate::forUser($user)->authorize('view', $ticket);

            return $ticket;
        }

        $modelClass = $this->modelClass($type);

        return $modelClass::query()->findOrFail($id);
    }

    public function key(string $type, int $id): string
    {
        return strtolower(str_replace('_', '-', $this->normalize($type))).'-'.$id;
    }

    public function title(string $type, Model $record): string
    {
        return match ($this->normalize($type)) {
            self::TASK => $record->name ?: 'Task #'.$record->id,
            self::EVENT, self::MEETING => $record->title ?: ucfirst(strtolower($type)).' #'.$record->id,
            self::CALL => $record->purpose ?: $record->contact ?: 'Call #'.$record->id,
            self::MESSAGE => $record->conversation_title ?: 'Message Conversation #'.$record->id,
            self::OFFICIAL_BUSINESS_TRAVEL => $record->purpose ?: $record->destination ?: 'Official Business #'.$record->id,
            self::OFFICIAL_BUSINESS_TRIP_TICKET => $record->reference_no ?: 'Trip Ticket #'.$record->id,
            self::EMAIL => $record->subject ?: 'Email #'.$record->id,
        };
    }
}
