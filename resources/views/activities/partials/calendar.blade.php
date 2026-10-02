<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <div class="flex flex-col gap-2 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-bold text-gray-900">
                JK&amp;C Google Calendar
            </h3>
            <p class="mt-1 text-xs text-gray-500">
                Tasks, meetings, and scheduled events are shown in Asia/Manila time.
            </p>
        </div>
        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-[10px] font-semibold text-emerald-700">
            <i class="fas fa-calendar-check"></i>
            Google Calendar
        </div>
    </div>
    <div class="bg-white p-2 sm:p-4">
        <template x-if="{{ $calendarVisible }}">
            <iframe
                src="https://calendar.google.com/calendar/embed?src=c_202fb00fab3d252dbc66929418b0edf779372e0165af84c0f5bdce4f1ca7d38d%40group.calendar.google.com&amp;ctz=Asia%2FManila"
                class="block min-h-[650px] w-full rounded-xl border-0"
                style="height: 760px;"
                frameborder="0"
                scrolling="no"
                title="JK&C Google Calendar"
            ></iframe>
        </template>
    </div>
</div>
