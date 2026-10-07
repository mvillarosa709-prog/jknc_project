@extends('layouts.app')
@section('title', 'Activities')

@section('content')
<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #cbd5e1;
    }
</style>
<div class="flex w-full flex-col lg:flex-row" x-data="activitiesData()" @view-master-official-business.window="openOfficialBusinessById($event.detail.masterId)">


    <!-- Left Sidebar -->
    <aside @click.capture="if ($event.target.closest('button') && hasActivityDetail) returnToActivities()" class="max-h-64 w-full shrink-0 overflow-y-auto border-b border-gray-200 bg-white pt-4 lg:min-h-[calc(100vh-4rem)] lg:w-52 lg:max-h-none lg:overflow-visible lg:border-b-0 lg:border-r">

        <!-- Activities sidebar -->
        <div>
            <div class="px-3 pb-2">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-1">
                    Activities
                </p>
            </div>

            <nav class="flex flex-col gap-0.5 px-2">

                <div>
                    <button
                        type="button"
                        @click="
                            activeTab = 'all';

                            selectedTaskDetails = null;
                            selectedMeetingDetails = null;
                            showVideoPlayer = false;
                        "
                        :class="activitySidebarGroup === 'all'
                            ? 'bg-blue-50 text-[#1d54e2] font-semibold border border-blue-100' : 'text-gray-700 hover:bg-gray-100'"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm transition-colors"
                    >
                        <span>All Activities</span>

                    </button>
                </div>

                <div>
                    <button
                        type="button"
                        @click="
                            if (activitySidebarGroup !== 'tasks') {
                                activeTab = 'task';
                                taskView = 'list';
                                tasksExpanded = true;
                            } else {
                                tasksExpanded = !tasksExpanded;
                            }

                            selectedTaskDetails = null;
                            selectedMeetingDetails = null;
                            showVideoPlayer = false;
                        "
                        :class="activitySidebarGroup === 'tasks'
                            ? 'bg-blue-50 text-[#1d54e2] font-semibold border border-blue-100' : 'text-gray-700 hover:bg-gray-100'"
                        :aria-expanded="activitySidebarGroup === 'tasks' && tasksExpanded"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm transition-colors"
                    >
                        <span>Tasks</span>

                        <i
                            class="fas fa-chevron-down text-[9px] transition-transform duration-200"
                            :class="tasksExpanded && activitySidebarGroup === 'tasks' ? 'rotate-180' : ''"
                        ></i>
                    </button>

                    <div
                        x-show="activitySidebarGroup === 'tasks' && tasksExpanded"
                        x-cloak
                        class="ml-3 mt-1 pl-3"
                    >
                        <button
                            type="button"
                            @click.stop="filterSelection.task = 'All Tasks'; taskOwnerFilter = 'all'; taskView = 'list'; currentPage = 1; tasksExpanded = true; selectedTaskDetails = null; selectedMeetingDetails = null; showVideoPlayer = false"
                            :class="filterSelection.task === 'All Tasks'
                                ? 'bg-blue-50 text-[#1d54e2] font-semibold'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                            class="mb-1 w-full rounded-lg px-3 py-2 text-left text-xs transition"
                        >
                            All Tasks
                        </button>

                        <button
                            type="button"
                            @click.stop="filterSelection.task = 'My Tasks'; taskView = 'list'; currentPage = 1; tasksExpanded = true; selectedTaskDetails = null; selectedMeetingDetails = null; showVideoPlayer = false"
                            :class="filterSelection.task === 'My Tasks'
                                ? 'bg-blue-50 text-[#1d54e2] font-semibold'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                            class="mb-1 w-full rounded-lg px-3 py-2 text-left text-xs transition"
                        >
                            My Tasks
                        </button>

                        <button
                            type="button"
                            @click.stop="filterSelection.task = 'Team Tasks'; taskView = 'list'; currentPage = 1; tasksExpanded = true; selectedTaskDetails = null; selectedMeetingDetails = null; showVideoPlayer = false"
                            :class="filterSelection.task === 'Team Tasks'
                                ? 'bg-blue-50 text-[#1d54e2] font-semibold'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                            class="w-full rounded-lg px-3 py-2 text-left text-xs transition"
                        >
                            Team Tasks
                        </button>
                    </div>
                </div>

                <button
                    @click="activeTab = 'events'; selectedTaskDetails = null; selectedMeetingDetails = null; showVideoPlayer = false"
                    :class="activeTab === 'events' ? 'bg-blue-50 text-[#1d54e2] font-semibold border border-blue-100' : 'text-gray-700 hover:bg-gray-100'"
                    class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors"
                >
                    Events
                </button>

                <button
                    @click="activeTab = 'call'; selectedTaskDetails = null; selectedMeetingDetails = null; showVideoPlayer = false"
                    :class="activeTab === 'call' ? 'bg-blue-50 text-[#1d54e2] font-semibold border border-blue-100' : 'text-gray-700 hover:bg-gray-100'"
                    class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors"
                >
                    Call
                </button>

                <button
                    @click="activeTab = 'meetings'; selectedTaskDetails = null; selectedMeetingDetails = null; showVideoPlayer = false"
                    :class="activeTab === 'meetings' ? 'bg-blue-50 text-[#1d54e2] font-semibold border border-blue-100' : 'text-gray-700 hover:bg-gray-100'"
                    class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors"
                >
                    Meetings
                </button>

                <button
                    @click="activeTab = 'emails'; selectedTaskDetails = null; selectedMeetingDetails = null; showVideoPlayer = false"
                    :class="activeTab === 'emails' ? 'bg-blue-50 text-[#1d54e2] font-semibold border border-blue-100' : 'text-gray-700 hover:bg-gray-100'"
                    class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors"
                >
                    Emails
                </button>

                                <div x-data="{ open: false }"
                    x-init="$watch('activeTab', value => { if (value === 'messages') open = true })">
                    <button
                        type="button"
                        @click="
                            activeTab = 'messages';
                            messageTab = 'messenger';
                            selectedTaskDetails = null;
                            selectedMeetingDetails = null;
                            showVideoPlayer = false;
                            open = !open
                        "
                        :class="activeTab === 'messages'
                            ? 'bg-blue-50 text-[#1d54e2] font-semibold border border-blue-100'
                            : 'text-gray-700 hover:bg-gray-100'"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm transition-colors"
                    >
                        <span>Messages</span>
                        <i
                            class="fas fa-chevron-down text-[10px] transition-transform"
                            :class="open ? 'rotate-180' : ''"
                        ></i>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        class="ml-2 mt-1 pl-2"
                    >
                        <button
                            type="button"
                            @click="activeTab = 'messages'; messageTab = 'messenger'"
                            :class="messageTab === 'messenger' ? 'bg-blue-50 text-[#1d54e2] font-semibold' : 'text-gray-600 hover:bg-gray-100'"
                            class="w-full rounded-lg px-3 py-1.5 text-left text-xs transition-colors"
                        >
                            Facebook Messenger
                        </button>
                    </div>
                </div>

                <div>
    <button
        @click="activeTab = 'official-business'; officialBusinessTab = 'all'; selectedTaskDetails = null; selectedMeetingDetails = null; showVideoPlayer = false"
        :class="activeTab === 'official-business' ? 'bg-blue-50 text-[#1d54e2] font-semibold border border-blue-100' : 'text-gray-700 hover:bg-gray-100'"
        class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors"
    >
        Official Business
    </button>

    <div
        x-show="activeTab === 'official-business'"
        x-cloak
        class="ml-2 mt-1 pl-2"
    >
        <button
            type="button"
            @click="officialBusinessTab = 'all'"
            :class="officialBusinessTab === 'all'
                ? 'bg-blue-50 text-[#1d54e2] font-semibold'
                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
            class="mb-1 w-full rounded-lg px-3 py-2 text-left text-xs transition"
        >
            All O.B.
        </button>

        <button
            type="button"
            @click="officialBusinessTab = 'mine'"
            :class="officialBusinessTab === 'mine'
                ? 'bg-blue-50 text-[#1d54e2] font-semibold'
                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
            class="mb-1 w-full rounded-lg px-3 py-2 text-left text-xs transition"
        >
            My O.B.
        </button>

        <button
            type="button"
            @click="officialBusinessTab = 'travel-desk'"
            :class="officialBusinessTab === 'travel-desk'
                ? 'bg-blue-50 text-[#1d54e2] font-semibold'
                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
            class="mb-1 w-full rounded-lg px-3 py-2 text-left text-xs transition"
        >
            Travel Desk
        </button>

        <button
            type="button"
            @click="officialBusinessTab = 'deleted'"
            :class="officialBusinessTab === 'deleted'
                ? 'bg-blue-50 text-[#1d54e2] font-semibold'
                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
            class="w-full rounded-lg px-3 py-2 text-left text-xs transition"
        >
            Deleted
        </button>
    </div>
</div>

                <!-- =====================================================
                     VISITOR LOG
                ====================================================== -->
                <div>

                    <!-- Parent -->
                    <button
                        type="button"
                        @click="
                            if (activeTab !== 'visitor-log') {
                                activeTab = 'visitor-log';
                                visitorLogTab = 'front-desk';
                                visitorLogExpanded = true;
                            } else {
                                visitorLogExpanded = !visitorLogExpanded;
                            }

                            selectedTaskDetails = null;
                            selectedMeetingDetails = null;
                            showVideoPlayer = false;
                        "
                        :class="activeTab === 'visitor-log'
                            ? 'bg-blue-50 text-[#1d54e2] font-semibold border border-blue-100'
                            : 'text-gray-700 hover:bg-gray-100'"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm transition-colors"
                    >
                        <span>Visitor Log</span>

                        <i
                            class="fas fa-chevron-down text-[9px] transition-transform duration-200"
                            :class="visitorLogExpanded && activeTab === 'visitor-log' ? 'rotate-180' : ''"
                        ></i>
                    </button>

                    <!-- Submenu -->
                    <div
                        x-show="activeTab === 'visitor-log' && visitorLogExpanded"
                        x-cloak
                        class="ml-3 mt-1 pl-3"
                    >

                        <!-- Front Desk -->
                        <button
                            type="button"
                            @click.stop="visitorLogTab = 'front-desk'; visitorLogExpanded = true"
                            :class="visitorLogTab === 'front-desk'
                                ? 'bg-blue-50 text-[#1d54e2] font-semibold'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                            class="mb-1 w-full rounded-lg px-3 py-2 text-left text-xs transition"
                        >
                            Front Desk
                        </button>

                        <!-- Visit Records -->
                        <button
                            type="button"
                            @click.stop="visitorLogTab = 'visit-records'; visitorLogExpanded = true"
                            :class="visitorLogTab === 'visit-records'
                                ? 'bg-blue-50 text-[#1d54e2] font-semibold'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                            class="w-full rounded-lg px-3 py-2 text-left text-xs transition"
                        >
                            Visit Records
                        </button>

                    </div>

                </div>

            </nav>

        </div>

    </aside>

    <!-- Main Content -->
    <div x-ref="activitiesMain" class="flex-1 min-w-0 w-full overflow-y-auto px-4 sm:px-6 py-4">

    <div x-show="hasActivityDetail" x-cloak>
        <button type="button" @click="returnToActivities()" class="mb-4 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            <i class="fas fa-arrow-left text-xs"></i>
            <span x-text="activityDetailBackLabel">Back to Activities</span>
        </button>
        @include('activities.partials.inline-details')
    </div>

    <div x-show="!hasActivityDetail">

    <!-- Main Card Container -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-6">

        <!-- Toolbar -->

            <!-- Left Toolbar --><div
    x-show="false"
    class="flex items-center justify-between px-4 py-3 border-b border-gray-100"
>
            <div class="flex items-center gap-3">


                <div class="relative" x-data="{
                        allTaskDropdownOpen: false,
                        allTaskTab: 'all_views'
                    }"
                    @click.away="allTaskDropdownOpen = false">

                    <button @click="allTaskDropdownOpen = !allTaskDropdownOpen" class="flex items-center gap-2 px-3 py-1.5 text-sm font-semibold text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">
                        <span x-text="filterSelection[activeTab]">All Tasks</span>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200" :class="allTaskDropdownOpen ? 'rotate-180' : ''"></i>
                    </button>

                    <!-- All Task Dropdown Panel -->
                    <div x-show="allTaskDropdownOpen"
                         style="display: none;"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute left-0 mt-2 w-64 rounded-xl shadow-xl bg-white ring-1 ring-blue-500 z-50 border border-blue-400 overflow-hidden">

                        <div class="flex flex-col h-full max-h-[400px]">

                            <!-- Content Area -->
                            <div class="flex-1 overflow-y-auto py-2">
                                <!-- All Views Content -->
                                <div>

                                    <div x-show="activeTab === 'task'" class="flex flex-col" role="menu" aria-orientation="vertical">
                                        <a href="#" @click.prevent="filterSelection.task = 'All Tasks'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">All Tasks</a>
                                        <a href="#" @click.prevent="filterSelection.task = 'Open Tasks'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">Open Tasks</a>
                                        <a href="#" @click.prevent="filterSelection.task = 'Closed Tasks'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">Closed Tasks</a>
                                        <a href="#" @click.prevent="filterSelection.task = 'Overdue Tasks'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">Overdue Tasks</a>
                                    </div>

                                    <div x-show="activeTab === 'events'" style="display: none;" class="flex flex-col" role="menu" aria-orientation="vertical">
                                        <a href="#" @click.prevent="filterSelection.events = 'All Events'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">All Events</a>
                                        <a href="#" @click.prevent="filterSelection.events = 'Upcoming Events'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">Upcoming Events</a>
                                        <a href="#" @click.prevent="filterSelection.events = 'Closed Events'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">Closed Events</a>
                                    </div>

                                    <div x-show="activeTab === 'call'" style="display: none;" class="flex flex-col" role="menu" aria-orientation="vertical">
                                        <a href="#" @click.prevent="filterSelection.call = 'All Calls'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">All Calls</a>
                                        <a href="#" @click.prevent="filterSelection.call = 'Inbound Calls'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">Inbound Calls</a>
                                        <a href="#" @click.prevent="filterSelection.call = 'Outbound Calls'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">Outbound Calls</a>
                                        <a href="#" @click.prevent="filterSelection.call = 'Scheduled Calls'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">Scheduled Calls</a>
                                        <a href="#" @click.prevent="filterSelection.call = 'Overdue Calls'; allTaskDropdownOpen = false" class="block px-8 py-2 text-sm text-gray-700 hover:bg-gray-100">Overdue Calls</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Toolbar -->
            <div class="flex items-center gap-3">
                <!-- Primary Action Button -->
                <button @click="activeTab === 'call' ? showCallModal = true : (activeTab === 'events' ? showEventModal = true : (activeTab === 'meetings' ? showMeetingModal = true : showTaskModal = true))" class="flex items-center gap-2 px-4 py-1.5 bg-[#1d54e2] text-white rounded-full text-sm font-medium hover:bg-[#1541b0] transition shadow-sm ml-2 z-10">
                    <i class="fas fa-plus text-xs"></i>
                    <span x-text="activeTab === 'events' ? 'Event' : (activeTab === 'call' ? 'Call' : 'Task')">Task</span>
                </button>

                <!-- 3 Dots Export Menu -->
                <div class="relative ml-1" x-data="{ exportDropdownOpen: false }" @click.away="exportDropdownOpen = false">
                    <button @click="exportDropdownOpen = !exportDropdownOpen" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded transition-colors">
                        <i class="fas fa-ellipsis-v text-sm"></i>
                    </button>

                    <div x-show="exportDropdownOpen"
                         style="display: none;"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 top-full mt-2 w-52 rounded-2xl shadow-xl bg-white ring-1 ring-black ring-opacity-5 z-50 overflow-hidden">
                        <div class="py-2">
                            <button @click="showExportModal = true; exportDropdownOpen = false;" class="w-full text-left px-5 py-3 hover:bg-gray-50 flex items-center gap-3 text-sm text-gray-700 font-medium">
                                <i class="fas fa-download text-gray-500"></i>
                                Export this View
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>


            <!-- =========================================================
     ALL ACTIVITIES V2
========================================================= -->
<div
    x-show="activeTab === 'all'"
    class="bg-[#f8fafc] min-h-[calc(100vh-8rem)]"
>
    <div class="px-6 py-6">

        <!-- =====================================================
             HEADER / SUMMARY
        ====================================================== -->
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">

            <div class="mb-5">
                <h1 class="text-[24px] font-bold leading-tight text-gray-900">
                    All Activities
                </h1>

                <p class="mt-1 text-xs text-gray-500">
                    Connected client-service activity ledger
                </p>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">

                <div class="rounded-lg bg-slate-50 px-4 py-3">
                    <div class="text-[10px] font-medium text-gray-500">
                        Activities
                    </div>

                    <div class="mt-1 text-xl font-bold text-gray-900" x-text="filteredAllActivities.length">
                        0
                    </div>
                </div>

                <div class="rounded-lg bg-slate-50 px-4 py-3">
                    <div class="text-[10px] font-medium text-gray-500">
                        Recorded Hours
                    </div>

                    <div class="mt-1 text-xl font-bold text-gray-900" x-text="formatActivityDuration(allActivitiesDurationMinutes)">
                        0m
                    </div>
                </div>

                <div class="rounded-lg bg-slate-50 px-4 py-3">
                    <div class="text-[10px] font-medium text-gray-500">
                        Activities with Duration
                    </div>

                    <div class="mt-1 text-xl font-bold text-gray-900" x-text="allActivitiesWithDuration + ' of ' + filteredAllActivities.length">
                        0 of 0
                    </div>
                </div>

                <div class="rounded-lg bg-slate-50 px-4 py-3">
                    <div class="text-[10px] font-medium text-gray-500">
                        Selected
                    </div>

                    <div class="mt-1 text-xl font-bold text-gray-900" x-text="selectedActivities.length">
                        0
                    </div>
                </div>

            </div>
        </div>


        <!-- =====================================================
             ACTIVITY LEDGER
        ====================================================== -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <!-- Filters -->
            <div class="border-b border-gray-200 p-4">

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">

                    <!-- Search -->
                    <div>
                        <input
                            type="text"
                            placeholder="Search title or summary"
                            x-model.debounce.250ms="allActivitiesSearch"
                            @input="currentPage = 1"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <!-- Account -->
                    <div>
                        <select
                            x-model="allActivitiesAccountFilter"
                            @change="currentPage = 1"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">All accounts</option>
                            <template x-for="account in allActivitiesAccounts" :key="account.id">
                                <option :value="String(account.id)" x-text="account.name"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Status -->
                    <div>
                        <select
                            x-model="allActivitiesStatusFilter"
                            @change="currentPage = 1"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">All statuses</option>
                            <template x-for="status in allActivitiesStatuses" :key="status">
                                <option :value="status" x-text="status"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Date -->
                    <div>
                        <select
                            x-model="allActivitiesDateFilter"
                            @change="allActivitiesMonthFilter = ''; allActivitiesDateFrom = ''; allActivitiesDateTo = ''; currentPage = 1"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">All dates</option>
                            <option value="today">Today</option>
                            <option value="last_7_days">Last 7 days</option>
                            <option value="last_30_days">Last 30 days</option>
                        </select>
                    </div>

                    <div>
                        <input
                            type="month"
                            x-model="allActivitiesMonthFilter"
                            @change="allActivitiesDateFilter = ''; allActivitiesDateFrom = ''; allActivitiesDateTo = ''"
                            aria-label="Activity month"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <div>
                        <input
                            type="date"
                            x-model="allActivitiesDateFrom"
                            @change="allActivitiesDateFilter = ''; allActivitiesMonthFilter = ''"
                            aria-label="Activity date from"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <div>
                        <input
                            type="date"
                            x-model="allActivitiesDateTo"
                            @change="allActivitiesDateFilter = ''; allActivitiesMonthFilter = ''"
                            aria-label="Activity date to"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <div>
                                                <select
                            :value="allActivitiesTypeFilters[0] || ''"
                            @change="allActivitiesTypeFilters = $event.target.value ? [$event.target.value] : []; currentPage = 1"
                            aria-label="Activity type"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">All Activities</option>
                            <option value="task">Tasks</option>
                            <option value="event">Events</option>
                            <option value="call">Calls</option>
                            <option value="meeting">Meetings</option>
                            <option value="message">Messages</option>
                            <option value="official_business_travel">Official Business</option>
                        </select>
                    </div>

                </div>

                <!-- Duration -->
                <div class="mt-3 w-full md:w-[280px] xl:w-[300px]">
                    <select
                        x-model="allActivitiesDurationFilter"
                        @change="currentPage = 1"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="">Any duration</option>
                        <option value="with">With duration</option>
                        <option value="without">Without duration</option>
                    </select>
                </div>

                <p x-show="allActivitiesReportError" x-text="allActivitiesReportError" class="mt-3 text-xs font-medium text-red-600"></p>

            </div>


            <!-- =================================================
                 TABLE
            ================================================== -->
            <div class="overflow-x-auto">

                <table class="min-w-full">

                    <thead class="bg-slate-50">
                        <tr class="border-b border-gray-200">

                                                    <th class="w-12 px-4 py-3 text-left">
                            <input
                                type="checkbox"
                                class="call-select-all h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                aria-label="Select all calls"
                            >
                        </th>
<th class="w-12 px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    :checked="allVisibleActivitiesSelected"
                                    @change="toggleVisibleActivities($event.target.checked)"
                                    class="h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Type
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Title / Summary
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Related Account
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Date
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Prepared By
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Duration
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Status
                            </th>

                            <th class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Actions
                            </th>

                        </tr>
                    </thead>


                    <tbody class="divide-y divide-gray-100">
                        <template x-for="activity in paginatedAllActivities" :key="activityKey(activity)">
                            <tr class="transition hover:bg-slate-50/70">
                                                            <td class="w-12 px-4 py-4">
                                <input
                                    type="checkbox"
                                    class="call-row-checkbox h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                    aria-label="Select call"
                                >
                            </td>
<td class="px-4 py-4">
                                    <input
                                        type="checkbox"
                                        x-model="selectedActivities"
                                        :value="activityKey(activity)"
                                        class="h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                </td>

                                <td class="whitespace-nowrap px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <i :class="activityIcon(activity.type)" class="text-[11px] text-slate-500"></i>
                                        <button
                                            type="button"
                                            @click="openActivityDetails(activity)"
                                            class="text-xs font-medium text-blue-600 underline-offset-2 transition hover:text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                            :aria-label="'View ' + formatActivityType(activity.type) + ' details'"
                                            x-text="formatActivityType(activity.type)"
                                        ></button>
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    <span class="text-xs font-semibold text-gray-900" x-text="activity.title"></span>
                                </td>

                                <td class="px-4 py-4 text-xs text-gray-600" x-text="relatedAccountLabel(activity) || '\u2014'"></td>

                                <td class="whitespace-nowrap px-4 py-4 text-xs text-gray-700" x-text="formatActivityDate(activity.occurred_at)"></td>

                                <td class="whitespace-nowrap px-4 py-4 text-xs text-gray-700" x-text="activity.owner || 'System'"></td>

                                <td class="whitespace-nowrap px-4 py-4 text-xs text-gray-700" x-text="activity.duration_minutes === null ? 'Not recorded' : formatActivityDuration(activity.duration_minutes)"></td>

                                <td class="px-4 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-medium"
                                        :class="activityStatusClass(activity.display_status)"
                                        x-text="activity.display_status || 'Not set'"
                                    ></span>
                                </td>

                                                                <td class="whitespace-nowrap px-4 py-4 text-right">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="openActivityDetails(activity)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-medium text-slate-600 transition hover:bg-slate-50"
                                            title="View activity"
                                        >
                                            <i class="far fa-eye"></i>
                                            <span>View</span>
                                        </button>

                                        <button
                                            type="button"
                                            x-show="canEditActivity(activity)"
                                            @click="editActivityFromAllActivities(activity)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-white px-2.5 py-1.5 text-[10px] font-medium text-blue-600 transition hover:bg-blue-50"
                                            title="Edit activity"
                                        >
                                            <i class="far fa-edit"></i>
                                            <span>Edit</span>
                                        </button>

                                        <button
                                            type="button"
                                            x-show="canMarkDoneActivity(activity)"
                                            @click="markActivityDone(activity)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-[10px] font-medium text-emerald-600 transition hover:bg-emerald-50"
                                            title="Mark activity as done"
                                        >
                                            <i class="fas fa-check"></i>
                                            <span>Done</span>
                                        </button>

                                        <button
                                            type="button"
                                            x-show="canDeleteCompletedActivity(activity)"
                                            @click="deleteCompletedActivity(activity)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-[10px] font-medium text-red-600 transition hover:bg-red-50"
                                            title="Delete completed activity"
                                        >
                                            <i class="far fa-trash-alt"></i>
                                            <span>Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="filteredAllActivities.length === 0">
                            <td colspan="9" class="px-4 py-8 text-center text-xs text-gray-500">
                                No activities match the current filters.
                            </td>
                        </tr>
                    </tbody>


                </table>

            </div>


            <!-- =================================================
                 TABLE FOOTER
            ================================================== -->
            <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">

                <div class="text-[11px] text-gray-500" x-text="allActivitiesPaginationText">
                    Showing 0 to 0 of 0 activities
                </div>

                <div class="flex items-center gap-1.5">

                    <button
                        type="button"
                        @click="if (currentPage > 1) currentPage--"
                        :disabled="currentPage <= 1"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-300"
                    >
                        <i class="fas fa-chevron-left text-[9px]"></i>
                    </button>

                    <button
                        type="button"
                        class="flex h-8 min-w-[32px] items-center justify-center rounded-lg bg-[#1d54e2] px-2 text-xs font-semibold text-white"
                    >
                        <span x-text="currentPage">1</span>
                    </button>

                    <button
                        type="button"
                        @click="if (currentPage < allActivitiesTotalPages) currentPage++"
                        :disabled="currentPage >= allActivitiesTotalPages"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-300"
                    >
                        <i class="fas fa-chevron-right text-[9px]"></i>
                    </button>

                </div>

            </div>

        </div>

    </div>
</div>
<!-- =========================================================
     TASKS V2
========================================================= -->
<div
    x-show="activeTab === 'task'"
    x-cloak
    style="display: none;"
    class="bg-white"
>
    <!-- Task Header -->
    <div class="px-4 sm:px-6 py-5 border-b border-gray-100">

        <!-- Title + Actions -->
        <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4">

            <!-- Title -->
            <div>
                <h1
    class="text-2xl font-bold text-slate-900"
    x-text="
        taskView === 'board'
            ? 'Board'
            : taskView === 'calendar'
                ? 'Calendar'
                : taskView === 'timeline'
                    ? 'Timeline'
                    : filterSelection.task === 'All Tasks'
                        ? 'All Task'
                        : filterSelection.task
    "
>
    All Task
</h1>

                <p class="mt-1 text-sm text-slate-500">
                    Organized task management.
                </p>
            </div>

            <!-- Header Actions -->
            <div class="flex flex-wrap items-center justify-end gap-2">


                <button
                    type="button"
                    @click="openTaskTool('bulk')"
                    class="h-9 px-4 rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition"
                >
                    Bulk Edit
                </button>

                <button
                    type="button"
                    @click="openTaskTool('templates')"
                    class="h-9 px-4 rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition"
                >
                    Templates
                </button>

                <button
                    type="button"
                    @click="openTaskTool('outline')"
                    class="h-9 px-4 rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition"
                >
                    Outline Builder
                </button>

                <button
                    type="button"
                    @click="showTaskModal = true"
                    class="h-9 px-4 rounded-lg bg-[#3157e2] text-xs font-semibold text-white hover:bg-[#2849c7] transition"
                >
                    + New Task
                </button>

            </div>
        </div>

        <!-- View Switcher -->
        <div class="mt-5 flex items-center justify-between gap-4">

            <div class="inline-flex items-center gap-1 p-1 bg-gray-100 rounded-lg">

                <button
                    type="button"
                    @click="taskView = 'list'"
                    :class="taskView === 'list'
                        ? 'bg-white text-[#1d54e2] shadow-sm'
                        : 'text-gray-500 hover:text-gray-800'"
                    class="px-3 py-2 rounded-md text-xs font-semibold transition"
                >
                    List
                </button>

                <button
                    type="button"
                    @click="taskView = 'board'"
                    :class="taskView === 'board'
                        ? 'bg-white text-[#1d54e2] shadow-sm'
                        : 'text-gray-500 hover:text-gray-800'"
                    class="px-3 py-2 rounded-md text-xs font-semibold transition"
                >
                    Board
                </button>

                <button
                    type="button"
                    @click="taskView = 'calendar'"
                    :class="taskView === 'calendar'
                        ? 'bg-white text-[#1d54e2] shadow-sm'
                        : 'text-gray-500 hover:text-gray-800'"
                    class="px-3 py-2 rounded-md text-xs font-semibold transition"
                >
                    Calendar
                </button>

            </div>
        </div>

        <!-- Search + Filters -->
        <div class="mt-4 flex flex-col lg:flex-row lg:items-center lg:justify-end gap-2">

            <!-- Search -->
            <div class="relative w-full lg:w-64">

                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>

                <input
                    type="search"
                    x-model.debounce.250ms="taskSearch"
                    @input="currentPage = 1"
                    placeholder="Search tasks"
                    class="w-full h-10 pl-9 pr-4 border border-gray-200 rounded-lg text-xs text-gray-700 placeholder-gray-400 outline-none focus:border-[#1d54e2] focus:ring-1 focus:ring-[#1d54e2]"
                >

            </div>

            <!-- Status -->
            <select
                x-model="taskStatusFilter"
                @change="currentPage = 1"
                class="h-10 min-w-[130px] border border-gray-200 rounded-lg bg-white px-3 text-xs text-gray-600 outline-none focus:border-[#1d54e2]"
            >
                <option value="all">All Statuses</option>
                <option value="open">Open</option>
                <option value="completed">Completed</option>
            </select>

            <!-- Priority -->
            <select
                x-model="taskPriorityFilter"
                @change="currentPage = 1"
                class="h-10 min-w-[130px] border border-gray-200 rounded-lg bg-white px-3 text-xs text-gray-600 outline-none focus:border-[#1d54e2]"
            >
                <option value="all">All Priorities</option>
                <option value="high">High</option>
                <option value="normal">Normal</option>
            </select>

        </div>

    </div>


    <!-- =====================================================
         LIST VIEW
    ====================================================== -->
    <div x-show="taskView === 'list'">

        <!-- Desktop table -->
        <div class="hidden md:block overflow-x-auto">


<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
<table class="w-full text-left border-collapse">

                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50/80">

                        <th class="w-12 px-4 py-3 text-center">
                            <input
                                type="checkbox"
                                x-model="selectAll"
                                @change="selectedTasks = selectAll
                                    ? filteredTasksList.map(task => task.id)
                                    : []"
                                class="rounded border-gray-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                            >
                        </th>

                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            Task
                        </th>

                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            Status
                        </th>

                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            Priority
                        </th>

                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            Due date
                        </th>

                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            Related to
                        </th>

                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            Assigned To
                        </th>

                        <th class="w-24 px-4 py-3"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">

                    <template
                        x-for="task in filteredTasksList.slice(
                            (currentPage - 1) * perPage,
                            currentPage * perPage
                        )"
                        :key="task.id"
                    >
                        <tr
                            class="group hover:bg-blue-50/30 transition"
                            :class="selectedTasks.includes(task.id)
                                ? 'bg-blue-50/50'
                                : ''"
                        >

                            <td class="px-4 py-4 text-center">
                                <input
                                    type="checkbox"
                                    :value="task.id"
                                    x-model="selectedTasks"
                                    class="rounded border-gray-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                >
                            </td>

                            <!-- Task -->
                            <td class="px-4 py-4">
    <div
        class="relative"
        :class="task.parent_task_id ? 'pl-8' : ''"
    >
        <div
            x-show="task.parent_task_id"
            class="pointer-events-none absolute left-1 top-0 h-full w-6"
            aria-hidden="true"
        >
            <span class="absolute left-2 top-0 h-1/2 border-l border-slate-300"></span>
            <span class="absolute left-2 top-1/2 w-4 border-t border-slate-300"></span>
        </div>

        <button
            type="button"
            @click="openTaskWorkspace(task)"
            class="min-w-0 text-left"
        >
            <div class="mb-1.5 flex flex-wrap items-center gap-2">
                <span
                    x-show="!task.parent_task_id"
                    class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-blue-600"
                >
                    Main Task
                </span>

                <span
                    x-show="task.parent_task_id"
                    class="inline-flex items-center rounded-full border border-violet-100 bg-violet-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-violet-600"
                >
                    Subtask
                </span>
            </div>

            <div
                class="text-sm font-semibold text-gray-900 transition hover:text-[#1d54e2]"
                x-text="task.name"
            ></div>

            <div
                x-show="task.parent_task_id"
                class="mt-1 max-w-[420px] truncate text-[10px] font-medium text-slate-400"
            >
                <span>Under: </span>
                <span
                    x-text="tasks.find(parent => Number(parent.id) === Number(task.parent_task_id))?.name || 'Main Task'"
                ></span>
            </div>

            <div
                x-show="task.description"
                class="mt-1 max-w-[420px] truncate text-xs text-gray-400"
                x-text="task.description"
            ></div>
        </button>
    </div>
</td>

                            <!-- Status -->
                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="task.status === 'Completed'
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-blue-50 text-blue-700'"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="task.status === 'Completed'
                                            ? 'bg-emerald-500'
                                            : 'bg-blue-500'"
                                    ></span>

                                    <span x-text="task.status || 'Open'"></span>
                                </span>
                            </td>

                            <!-- Priority -->
                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="task.priority === 'High'
                                        ? 'bg-red-50 text-red-700'
                                        : 'bg-gray-100 text-gray-600'"
                                    x-text="task.priority || 'Normal'"
                                ></span>
                            </td>

                            <!-- Due -->
                            <td class="px-4 py-4 text-sm text-gray-600">
                                <div class="flex items-center gap-2">
                                    <i class="far fa-calendar text-gray-400"></i>
                                    <span x-text="task.due_date || 'No due date'"></span>
                                </div>
                            </td>

                            <!-- Related -->
                            <td class="px-4 py-4 text-sm text-gray-600">
                                <span
                                    class="block max-w-[180px] truncate"
                                    x-text="task.related_to || '-'"
                                ></span>
                            </td>

                            <!-- Owner -->
                            <td class="px-4 py-4">
                                <template x-if="task.owner">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#D1F1DE] text-[#0A5632] text-[10px] font-bold">
                                            <span
                                                x-text="task.owner
                                                    .split(' ')
                                                    .map(n => n[0])
                                                    .join('')
                                                    .substring(0, 2)
                                                    .toUpperCase()"
                                            ></span>
                                        </span>

                                        <span
                                            class="text-xs font-medium text-gray-700"
                                            x-text="task.owner"
                                        ></span>
                                    </div>
                                </template>

                                <span
                                    x-show="!task.owner"
                                    class="text-sm text-gray-400"
                                >
                                    &mdash;
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-1 opacity-60 group-hover:opacity-100 transition">

                                    <button
                                        type="button"
                                        @click="editTask(task)"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-blue-50 hover:text-[#1d54e2] transition"
                                        title="Edit task"
                                    >
                                        <i class="fas fa-pencil-alt text-xs"></i>
                                    </button>

                                    <button
                                        type="button"
                                        @click="deleteActivity(task.id, 'tasks')"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition"
                                        title="Delete task"
                                    >
                                        <i class="far fa-trash-alt text-xs"></i>
                                    </button>

                                </div>
                            </td>

                        </tr>
                    </template>

                </tbody>
            </table>
        </div>
        </div>


        <!-- Mobile cards -->
        <div class="md:hidden divide-y divide-gray-100">

            <template
                x-for="task in filteredTasksList.slice(
                    (currentPage - 1) * perPage,
                    currentPage * perPage
                )"
                :key="'mobile-' + task.id"
            >
                <div class="p-4">

                    <div class="flex items-start gap-3">

                        <input
                            type="checkbox"
                            :value="task.id"
                            x-model="selectedTasks"
                            class="mt-1 rounded border-gray-300 text-[#1d54e2]"
                        >

                        <div class="min-w-0 flex-1">

                            <div class="flex items-start justify-between gap-3">

                                <button
                                    type="button"
                                    @click="openTaskWorkspace(task)"
                                    class="min-w-0 text-left"
                                >
                                    <div
                                        class="truncate text-sm font-semibold text-gray-900"
                                        x-text="task.name"
                                    ></div>
                                </button>

                                <button
                                    type="button"
                                    @click="editTask(task)"
                                    class="shrink-0 text-gray-400 hover:text-[#1d54e2]"
                                >
                                    <i class="fas fa-pencil-alt text-xs"></i>
                                </button>

                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">

                                <span
                                    class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                    :class="task.status === 'Completed'
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-blue-50 text-blue-700'"
                                    x-text="task.status || 'Open'"
                                ></span>

                                <span
                                    class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                    :class="task.priority === 'High'
                                        ? 'bg-red-50 text-red-700'
                                        : 'bg-gray-100 text-gray-600'"
                                    x-text="task.priority || 'Normal'"
                                ></span>

                            </div>

                            <div class="mt-3 space-y-1.5 text-xs text-gray-500">

                                <div class="flex items-center gap-2">
                                    <i class="far fa-calendar w-4 text-gray-400"></i>
                                    <span x-text="task.due_date || 'No due date'"></span>
                                </div>

                                <div
                                    x-show="task.owner"
                                    class="flex items-center gap-2"
                                >
                                    <i class="far fa-user w-4 text-gray-400"></i>
                                    <span x-text="task.owner"></span>
                                </div>

                                <div
                                    x-show="task.related_to"
                                    class="flex items-center gap-2"
                                >
                                    <i class="fas fa-link w-4 text-gray-400"></i>
                                    <span
                                        class="truncate"
                                        x-text="task.related_to"
                                    ></span>
                                </div>

                            </div>

                        </div>
                    </div>

                </div>
            </template>

        </div>


        <!-- Empty state -->
        <div
            x-show="filteredTasksList.length === 0"
            class="px-6 py-16 text-center"
        >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <i class="fas fa-check-double"></i>
            </div>

            <h3 class="mt-4 text-sm font-semibold text-gray-900">
                No tasks found
            </h3>

            <p class="mt-1 text-sm text-gray-500">
                Try changing your filters or create a new task.
            </p>

        </div>

    </div>
    <!-- =====================================================
         BOARD VIEW
    ====================================================== -->
    <div x-show="taskView === 'board'" x-cloak class="p-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <template x-for="column in taskBoardColumns" :key="column.status">
                <section
                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                    @dragover.prevent
                    @drop.prevent="draggedTaskId && moveTaskStatus(tasks.find(task => Number(task.id) === Number(draggedTaskId)), column.status); draggedTaskId = null"
                >
                    <header class="flex items-center justify-between border-b border-gray-100 px-4 py-3.5">
                        <div class="flex min-w-0 items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-blue-500"></span><h3 class="truncate text-xs font-bold text-gray-900" x-text="column.status"></h3></div>
                        <span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-bold text-blue-700" x-text="boardTasks(column.status).length"></span>
                    </header>
                    <div class="min-h-[220px] space-y-3 p-3">
                        <template x-for="task in boardTasks(column.status)" :key="`workflow-${column.status}-${task.id}`">
                            <article draggable="true" @dragstart="draggedTaskId = task.id" @dragend="draggedTaskId = null" class="group rounded-xl border border-gray-200 bg-white p-3 transition hover:border-blue-200 hover:shadow-md">
                                <button type="button" @click="openTaskWorkspace(task)" class="w-full text-left"><h4 class="text-sm font-semibold text-gray-900" x-text="task.name"></h4><p x-show="task.related_to" class="mt-1 truncate text-[10px] text-gray-400" x-text="task.related_to"></p></button>
                                <div class="mt-3 flex items-center justify-between gap-2"><span class="rounded-full bg-gray-100 px-2 py-1 text-[9px] font-semibold text-gray-600" x-text="task.priority || 'Normal'"></span><span class="text-[9px] text-gray-400" x-text="task.due_date || 'No due date'"></span></div>
                                <label class="mt-3 block text-[9px] font-semibold text-slate-500 sm:hidden">Move To
                                    <select :value="task.status || 'Open'" @change="moveTaskStatus(task, $event.target.value)" class="mt-1 h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-[10px] text-slate-700">
                                        <template x-for="status in taskStatusValues" :key="status"><option :value="status" x-text="status"></option></template>
                                    </select>
                                </label>
                            </article>
                        </template>
                        <p x-show="!boardTasks(column.status).length" class="px-3 py-10 text-center text-xs text-gray-400">No tasks</p>
                    </div>
                </section>
            </template>
        </div>
    </div>

    <!-- Retained legacy grouping is hidden while the V2 workflow board above is active. -->
    <div
        x-show="false"
        x-cloak
        class="p-4"
    >
        <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">

            <!-- OPEN -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                <header class="flex items-center justify-between border-b border-gray-100 px-4 py-3.5">
                    <div class="flex items-center gap-2.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>

                        <h3 class="text-sm font-bold text-gray-900">
                            Open
                        </h3>

                        <span
                            class="flex h-6 min-w-[24px] items-center justify-center rounded-full bg-blue-50 px-2 text-[11px] font-bold text-blue-700"
                            x-text="filteredTasksList.filter(task =>
                                task.status !== 'Completed' &&
                                !isTaskOverdue(task)
                            ).length"
                        ></span>
                    </div>
                </header>

                <div class="min-h-[260px] space-y-3 p-3">

                    <template
                        x-for="task in filteredTasksList.filter(task =>
                            task.status !== 'Completed' &&
                            !isTaskOverdue(task)
                        )"
                        :key="'board-open-' + task.id"
                    >
                        <article
                            class="group rounded-xl border border-gray-200 bg-white p-4 transition hover:border-blue-200 hover:shadow-md"
                        >
                            <div class="flex items-start justify-between gap-3">

                                <button
                                    type="button"
                                    @click="openTaskWorkspace(task)"
                                    class="min-w-0 flex-1 text-left"
                                >
                                    <h4
                                        class="text-sm font-semibold text-gray-900 transition group-hover:text-[#1d54e2]"
                                        x-text="task.name"
                                    ></h4>

                                    <p
                                        x-show="task.description"
                                        class="mt-1 line-clamp-2 text-xs leading-5 text-gray-400"
                                        x-text="task.description"
                                    ></p>
                                </button>

                                <button
                                    type="button"
                                    @click="editTask(task)"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-blue-50 hover:text-[#1d54e2]"
                                    title="Edit task"
                                >
                                    <i class="fas fa-pencil-alt text-xs"></i>
                                </button>

                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span
                                    class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                    :class="task.priority === 'High'
                                        ? 'bg-red-50 text-red-700'
                                        : 'bg-gray-100 text-gray-600'"
                                    x-text="task.priority || 'Normal'"
                                ></span>
                            </div>

                            <div class="mt-4 space-y-2 text-xs text-gray-500">

                                <div class="flex items-center gap-2">
                                    <i class="far fa-calendar w-4 text-gray-400"></i>
                                    <span x-text="task.due_date || 'No due date'"></span>
                                </div>

                                <div
                                    x-show="task.owner"
                                    class="flex items-center gap-2"
                                >
                                    <i class="far fa-user w-4 text-gray-400"></i>
                                    <span class="truncate" x-text="task.owner"></span>
                                </div>

                                <div
                                    x-show="task.related_to"
                                    class="flex items-center gap-2"
                                >
                                    <i class="fas fa-link w-4 text-gray-400"></i>
                                    <span class="truncate" x-text="task.related_to"></span>
                                </div>

                            </div>
                        </article>
                    </template>

                    <div
                        x-show="filteredTasksList.filter(task =>
                            task.status !== 'Completed' &&
                            !isTaskOverdue(task)
                        ).length === 0"
                        class="px-4 py-12 text-center"
                    >
                        <i class="far fa-check-circle text-xl text-gray-300"></i>
                        <p class="mt-2 text-xs font-medium text-gray-400">
                            No open tasks
                        </p>
                    </div>

                </div>
            </section>


            <!-- OVERDUE -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                <header class="flex items-center justify-between border-b border-gray-100 px-4 py-3.5">
                    <div class="flex items-center gap-2.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>

                        <h3 class="text-sm font-bold text-gray-900">
                            Overdue
                        </h3>

                        <span
                            class="flex h-6 min-w-[24px] items-center justify-center rounded-full bg-red-50 px-2 text-[11px] font-bold text-red-700"
                            x-text="filteredTasksList.filter(task =>
                                isTaskOverdue(task)
                            ).length"
                        ></span>
                    </div>
                </header>

                <div class="min-h-[260px] space-y-3 p-3">

                    <template
                        x-for="task in filteredTasksList.filter(task =>
                            isTaskOverdue(task)
                        )"
                        :key="'board-overdue-' + task.id"
                    >
                        <article
                            class="group rounded-xl border border-red-100 bg-white p-4 transition hover:border-red-200 hover:shadow-md"
                        >
                            <div class="flex items-start justify-between gap-3">

                                <button
                                    type="button"
                                    @click="openTaskWorkspace(task)"
                                    class="min-w-0 flex-1 text-left"
                                >
                                    <h4
                                        class="text-sm font-semibold text-gray-900 transition group-hover:text-red-600"
                                        x-text="task.name"
                                    ></h4>

                                    <p
                                        x-show="task.description"
                                        class="mt-1 line-clamp-2 text-xs leading-5 text-gray-400"
                                        x-text="task.description"
                                    ></p>
                                </button>

                                <button
                                    type="button"
                                    @click="editTask(task)"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-600"
                                    title="Edit task"
                                >
                                    <i class="fas fa-pencil-alt text-xs"></i>
                                </button>

                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">

                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700">
                                    Overdue
                                </span>

                                <span
                                    class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                    :class="task.priority === 'High'
                                        ? 'bg-red-50 text-red-700'
                                        : 'bg-gray-100 text-gray-600'"
                                    x-text="task.priority || 'Normal'"
                                ></span>

                            </div>

                            <div class="mt-4 space-y-2 text-xs text-gray-500">

                                <div class="flex items-center gap-2 text-red-600">
                                    <i class="far fa-calendar w-4"></i>
                                    <span x-text="task.due_date || 'No due date'"></span>
                                </div>

                                <div
                                    x-show="task.owner"
                                    class="flex items-center gap-2"
                                >
                                    <i class="far fa-user w-4 text-gray-400"></i>
                                    <span class="truncate" x-text="task.owner"></span>
                                </div>

                                <div
                                    x-show="task.related_to"
                                    class="flex items-center gap-2"
                                >
                                    <i class="fas fa-link w-4 text-gray-400"></i>
                                    <span class="truncate" x-text="task.related_to"></span>
                                </div>

                            </div>
                        </article>
                    </template>

                    <div
                        x-show="filteredTasksList.filter(task =>
                            isTaskOverdue(task)
                        ).length === 0"
                        class="px-4 py-12 text-center"
                    >
                        <i class="far fa-clock text-xl text-gray-300"></i>
                        <p class="mt-2 text-xs font-medium text-gray-400">
                            No overdue tasks
                        </p>
                    </div>

                </div>
            </section>


            <!-- COMPLETED -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                <header class="flex items-center justify-between border-b border-gray-100 px-4 py-3.5">
                    <div class="flex items-center gap-2.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>

                        <h3 class="text-sm font-bold text-gray-900">
                            Completed
                        </h3>

                        <span
                            class="flex h-6 min-w-[24px] items-center justify-center rounded-full bg-emerald-50 px-2 text-[11px] font-bold text-emerald-700"
                            x-text="filteredTasksList.filter(task =>
                                task.status === 'Completed'
                            ).length"
                        ></span>
                    </div>
                </header>

                <div class="min-h-[260px] space-y-3 p-3">

                    <template
                        x-for="task in filteredTasksList.filter(task =>
                            task.status === 'Completed'
                        )"
                        :key="'board-completed-' + task.id"
                    >
                        <article
                            class="group rounded-xl border border-gray-200 bg-white p-4 opacity-80 transition hover:opacity-100 hover:shadow-md"
                        >
                            <div class="flex items-start justify-between gap-3">

                                <button
                                    type="button"
                                    @click="openTaskWorkspace(task)"
                                    class="min-w-0 flex-1 text-left"
                                >
                                    <h4
                                        class="text-sm font-semibold text-gray-600 line-through"
                                        x-text="task.name"
                                    ></h4>

                                    <p
                                        x-show="task.description"
                                        class="mt-1 line-clamp-2 text-xs leading-5 text-gray-400"
                                        x-text="task.description"
                                    ></p>
                                </button>

                                <button
                                    type="button"
                                    @click="editTask(task)"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-emerald-50 hover:text-emerald-600"
                                    title="Edit task"
                                >
                                    <i class="fas fa-pencil-alt text-xs"></i>
                                </button>

                            </div>

                            <div class="mt-4">
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                    Completed
                                </span>
                            </div>

                            <div class="mt-4 space-y-2 text-xs text-gray-500">

                                <div class="flex items-center gap-2">
                                    <i class="far fa-calendar w-4 text-gray-400"></i>
                                    <span x-text="task.due_date || 'No due date'"></span>
                                </div>

                                <div
                                    x-show="task.owner"
                                    class="flex items-center gap-2"
                                >
                                    <i class="far fa-user w-4 text-gray-400"></i>
                                    <span class="truncate" x-text="task.owner"></span>
                                </div>

                                <div
                                    x-show="task.related_to"
                                    class="flex items-center gap-2"
                                >
                                    <i class="fas fa-link w-4 text-gray-400"></i>
                                    <span class="truncate" x-text="task.related_to"></span>
                                </div>

                            </div>
                        </article>
                    </template>

                    <div
                        x-show="filteredTasksList.filter(task =>
                            task.status === 'Completed'
                        ).length === 0"
                        class="px-4 py-12 text-center"
                    >
                        <i class="fas fa-check text-xl text-gray-300"></i>
                        <p class="mt-2 text-xs font-medium text-gray-400">
                            No completed tasks
                        </p>
                    </div>

                </div>
            </section>

        </div>
    </div>
    <!-- =====================================================
         CALENDAR VIEW
    ====================================================== -->
    <div
        x-show="taskView === 'calendar'"

        class="p-4"
    >
        @include('activities.partials.calendar', ['calendarVisible' => "taskView === 'calendar'"])
    </div>

    <!-- =====================================================
         TIMELINE VIEW
    ====================================================== -->
    <div
        x-show="taskView === 'timeline'"
        x-cloak
        class="bg-slate-50/70 px-4 py-5 sm:px-6"
    >
        <!-- Timeline toolbar -->
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

            <div>
                <h3 class="text-base font-bold text-slate-900">
                    Task Timeline
                </h3>

                <p class="mt-1 text-xs text-slate-500">
                    Tasks arranged by due date.
                </p>
            </div>

            <div class="flex items-center gap-2">

                <button
                    type="button"
                    @click="timelineScope = 'all'"
                    :class="timelineScope === 'all'
                        ? 'border-blue-200 bg-blue-50 text-[#1d54e2]'
                        : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50'"
                    class="rounded-lg border px-3 py-2 text-xs font-semibold transition"
                >
                    All
                </button>

                <button
                    type="button"
                    @click="timelineScope = 'open'"
                    :class="timelineScope === 'open'
                        ? 'border-blue-200 bg-blue-50 text-[#1d54e2]'
                        : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50'"
                    class="rounded-lg border px-3 py-2 text-xs font-semibold transition"
                >
                    Open
                </button>

                <button
                    type="button"
                    @click="timelineScope = 'completed'"
                    :class="timelineScope === 'completed'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50'"
                    class="rounded-lg border px-3 py-2 text-xs font-semibold transition"
                >
                    Completed
                </button>

            </div>
        </div>


        <!-- Timeline -->
        <div class="relative">

            <!-- Vertical line -->
            <div
                class="absolute bottom-0 left-[18px] top-0 w-px bg-gray-200 sm:left-[22px]"
                aria-hidden="true"
            ></div>


            <div class="space-y-4">

                <template
                    x-for="task in timelineTasks"
                    :key="'timeline-' + task.id"
                >
                    <article class="relative pl-12 sm:pl-14">

                        <!-- Timeline marker -->
                        <div
                            class="absolute left-0 top-3 flex h-9 w-9 items-center justify-center rounded-full border-4 border-slate-50 sm:h-11 sm:w-11"
                            :class="{
                                'bg-emerald-100 text-emerald-700': task.status === 'Completed',
                                'bg-red-100 text-red-700': task.status !== 'Completed' && isTaskOverdue(task),
                                'bg-blue-100 text-blue-700': task.status !== 'Completed' && !isTaskOverdue(task)
                            }"
                        >
                            <i
                                class="text-xs sm:text-sm"
                                :class="{
                                    'fas fa-check': task.status === 'Completed',
                                    'far fa-clock': task.status !== 'Completed' && isTaskOverdue(task),
                                    'far fa-calendar': task.status !== 'Completed' && !isTaskOverdue(task)
                                }"
                            ></i>
                        </div>


                        <!-- Card -->
                        <div
                            class="rounded-xl border bg-white p-4 shadow-sm transition hover:shadow-md"
                            :class="{
                                'border-emerald-100': task.status === 'Completed',
                                'border-red-100': task.status !== 'Completed' && isTaskOverdue(task),
                                'border-gray-200': task.status !== 'Completed' && !isTaskOverdue(task)
                            }"
                        >

                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">

                                <!-- Main info -->
                                <div class="min-w-0 flex-1">

                                    <div class="flex flex-wrap items-center justify-end gap-2">

                                        <button
                                            type="button"
                                            @click="openTaskWorkspace(task)"
                                            class="truncate text-left text-sm font-bold text-gray-900 hover:text-[#1d54e2] transition"
                                        >
                                            <span x-text="task.name"></span>
                                        </button>


                                        <!-- Status -->
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                            :class="task.status === 'Completed'
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-blue-50 text-blue-700'"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full"
                                                :class="task.status === 'Completed'
                                                    ? 'bg-emerald-500'
                                                    : 'bg-blue-500'"
                                            ></span>

                                            <span x-text="task.status || 'Open'"></span>
                                        </span>


                                        <!-- Priority -->
                                        <span
                                            class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                            :class="task.priority === 'High'
                                                ? 'bg-red-50 text-red-700'
                                                : 'bg-gray-100 text-gray-600'"
                                            x-text="task.priority || 'Normal'"
                                        ></span>

                                    </div>


                                    <p
                                        x-show="task.description"
                                        class="mt-2 max-w-3xl text-xs leading-5 text-gray-500"
                                        x-text="task.description"
                                    ></p>


                                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-500">

                                        <div class="flex items-center gap-2">
                                            <i class="far fa-calendar w-4 text-gray-400"></i>

                                            <span
                                                :class="task.status !== 'Completed' && isTaskOverdue(task)
                                                    ? 'font-semibold text-red-600'
                                                    : ''"
                                                x-text="task.due_date || 'No due date'"
                                            ></span>
                                        </div>


                                        <div
                                            x-show="task.owner"
                                            class="flex items-center gap-2"
                                        >
                                            <i class="far fa-user w-4 text-gray-400"></i>
                                            <span x-text="task.owner"></span>
                                        </div>


                                        <div
                                            x-show="task.related_to"
                                            class="flex min-w-0 items-center gap-2"
                                        >
                                            <i class="fas fa-link w-4 text-gray-400"></i>

                                            <span
                                                class="max-w-[240px] truncate"
                                                x-text="task.related_to"
                                            ></span>
                                        </div>

                                    </div>

                                </div>


                                <!-- Actions -->
                                <div class="flex shrink-0 items-center gap-1">

                                    <button
                                        type="button"
                                        @click="editTask(task)"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-blue-50 hover:text-[#1d54e2]"
                                        title="Edit task"
                                    >
                                        <i class="fas fa-pencil-alt text-xs"></i>
                                    </button>

                                    <button
                                        type="button"
                                        @click="deleteActivity(task.id, 'tasks')"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-600"
                                        title="Delete task"
                                    >
                                        <i class="far fa-trash-alt text-xs"></i>
                                    </button>

                                </div>

                            </div>

                        </div>

                    </article>
                </template>


                <!-- Empty state -->
                <div
                    x-show="timelineTasks.length === 0"
                    class="rounded-xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center"
                >
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                        <i class="fas fa-stream"></i>
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-gray-900">
                        No timeline tasks
                    </h3>

                    <p class="mt-1 text-sm text-gray-500">
                        No tasks match the current timeline filter.
                    </p>
                </div>

            </div>

        </div>

    </div>

</div>


</div>
<!-- =========================================================
     EVENTS / CALLS LEGACY TABLE
========================================================= -->
<div
    x-show="activeTab === 'events' || activeTab === 'call'"
    class="overflow-x-auto"
>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

<div x-show="activeTab === 'events'" style="display:none;">

    <!-- =====================================================
         EVENTS
    ====================================================== -->
    <div class="p-4">

        <!-- Header -->
        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

            <div>
                <h2 class="text-xl font-bold tracking-tight text-gray-900">
                    Events
                </h2>

                <p class="mt-1 text-xs text-gray-500">
                    Plan, document, and report trainings, seminars, conferences, client events, and other activities.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">


<button
                    type="button"
                    @click="showExportModal = true"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-700 transition hover:border-blue-300 hover:text-[#1d54e2] focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    <i class="fas fa-file-export text-[10px]"></i>
                    Reports
                </button>

                                <button
                    type="button"
                    class="event-ui-template-button inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    Templates
                </button>

                <button
                    type="button"
                    @click="showExportModal = true"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    <i class="fas fa-download text-[10px]"></i>
                    Export
                </button>
<button
                    type="button"
                    @click="showEventModal = true"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#1d54e2] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1541b0] focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    <i class="fas fa-plus text-[10px]"></i>
                    New Event
                </button>

            </div>
        </div>


        <!-- =================================================
             EVENTS CARD
        ================================================== -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <!-- Filters -->
            <div class="flex flex-col gap-4 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between">

                <!-- View tabs -->
                <div class="inline-flex w-fit shrink-0 rounded-lg bg-gray-100 p-1">

                    <button
                        type="button"
                        @click="eventViewTab = 'all'; currentPage = 1"
                        :class="eventViewTab === 'all'
                            ? 'bg-white text-[#1d54e2] shadow-sm'
                            : 'text-gray-500 hover:text-gray-800'"
                        class="rounded-md px-3 py-2 text-[10px] font-semibold transition"
                    >
                        All Events
                    </button>

                    <button
                        type="button"
                        @click="eventViewTab = 'upcoming'; currentPage = 1"
                        :class="eventViewTab === 'upcoming'
                            ? 'bg-white text-[#1d54e2] shadow-sm'
                            : 'text-gray-500 hover:text-gray-800'"
                        class="rounded-md px-3 py-2 text-[10px] font-semibold transition"
                    >
                        Upcoming
                    </button>

                    <button
                        type="button"
                        @click="eventViewTab = 'completed'; currentPage = 1"
                        :class="eventViewTab === 'completed'
                            ? 'bg-white text-[#1d54e2] shadow-sm'
                            : 'text-gray-500 hover:text-gray-800'"
                        class="rounded-md px-3 py-2 text-[10px] font-semibold transition"
                    >
                        Completed
                    </button>

                </div>


                <!-- Search / filters -->
                <div class="grid w-full grid-cols-1 gap-3 md:grid-cols-3 lg:max-w-[820px]">

                    <div class="relative">

                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-300"></i>

                        <input
                            type="text"
                            x-model.debounce.300ms="eventSearch"
                            @input="currentPage = 1"
                            placeholder="Search events"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-3 text-xs text-gray-700 outline-none placeholder:text-gray-400 transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >

                    </div>

                    <select
                        x-model="eventModeFilter"
                        @change="currentPage = 1"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="all">All Modes</option>
                        <option value="Physical">Physical</option>
                        <option value="Online">Online</option>
                        <option value="Hybrid">Hybrid</option>
                    </select>

                    <select
                        x-model="eventStatusFilter"
                        @change="currentPage = 1"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="all">All Statuses</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>

                </div>
            </div>


            <!-- =================================================
                 EVENTS TABLE
            ================================================== -->
            <div class="overflow-x-auto">

                <table class="w-full min-w-[1320px] table-auto text-left">

                    <thead>
                        <tr class="border-b border-gray-200 bg-slate-50">

                                                        <th class="w-12 px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    class="event-select-all h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                    aria-label="Select all events"
                                >
                            </th>
<th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Event
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Related Account
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Event Type
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Date / Time
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Venue / Mode
                            </th>

                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Participants
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                JK&amp;C Role
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Status
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Estimated
                            </th>

                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Actual
                            </th>

                            <th class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Actions
                            </th>

                        </tr>
                    </thead>


                    <tbody class="divide-y divide-gray-100">

                        <template
                            x-for="event in filteredEventsList.slice((currentPage - 1) * perPage, currentPage * perPage)"
                            :key="event.id"
                        >

                            <tr class="bg-white transition hover:bg-blue-50/20">

                                <!-- Event -->
                                                                <td class="w-12 px-4 py-4">
                                    <input
                                        type="checkbox"
                                        class="event-row-checkbox h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                        aria-label="Select event"
                                    >
                                </td>
<td class="px-3 py-3 align-middle">

                                    <button
                                        type="button"
                                        @click="openCoreActivityDetails('event', event)"
                                        class="block max-w-[230px] text-left"
                                    >

                                        <div
                                            class="truncate text-[10px] font-bold text-[#1d54e2] hover:underline"
                                            x-text="event.title"
                                        ></div>

                                        <div
                                            class="mt-1 truncate text-[8px] text-gray-400"
                                            x-text="event.host
                                                ? 'Organized by ' + event.host
                                                : 'Event activity'"
                                        ></div>

                                    </button>

                                </td>


                                <!-- Related Account -->
                                <td
                                    class="px-3 py-3 text-[9px] text-gray-700"
                                    x-text="event.related_to || '-'"
                                ></td>


                                <!-- Event Type -->
                                <td
                                    class="px-3 py-3 text-[9px] text-gray-700"
                                    x-text="event.event_type || event.type || '-'"
                                ></td>


                                <!-- Date / Time -->
                                <td class="px-3 py-3">

                                    <div
                                        class="text-[9px] font-medium text-gray-700"
                                        x-text="event.from || '-'"
                                    ></div>

                                    <div
                                        x-show="event.start_time || event.time"
                                        class="mt-1 text-[8px] text-gray-400"
                                        x-text="event.start_time || event.time || ''"
                                    ></div>

                                </td>


                                <!-- Venue / Mode -->
                                <td class="px-3 py-3">

                                    <span
                                        class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-[8px] font-semibold text-gray-600"
                                        x-text="event.mode || 'Physical'"
                                    ></span>

                                    <div
                                        class="mt-1 max-w-[170px] truncate text-[8px] text-gray-400"
                                        x-text="event.venue || event.location || '-'"
                                    ></div>

                                </td>


                                <!-- Participants -->
                                <td
                                    class="px-3 py-3 text-center text-[9px] font-medium text-gray-700"
                                    x-text="event.participants || '-'"
                                ></td>


                                <!-- JK&C Role -->
                                <td
                                    class="px-3 py-3 text-[9px] text-gray-700"
                                    x-text="event.role || '-'"
                                ></td>


                                <!-- Status -->
                                <td class="px-3 py-3">

                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-[8px] font-semibold"
                                        :class="{
                                            'bg-blue-50 text-blue-600':
                                                !event.status || event.status === 'Scheduled',

                                            'bg-emerald-50 text-emerald-700':
                                                event.status === 'Completed',

                                            'bg-red-50 text-red-600':
                                                event.status === 'Cancelled'
                                        }"
                                        x-text="event.status || 'Scheduled'"
                                    ></span>

                                </td>


                                <!-- Estimated -->
                                <td
                                    class="px-3 py-3 text-[9px] text-gray-700"
                                    x-text="event.estimated_hours
                                        ? event.estimated_hours + 'h'
                                        : '\u2014'"
                                ></td>


                                <!-- Actual -->
                                <td
                                    class="px-3 py-3 text-[9px] text-gray-700"
                                    x-text="event.actual_hours
                                        ? event.actual_hours + 'h'
                                        : '\u2014'"
                                ></td>


                                <!-- Actions -->
                                <td class="px-3 py-3 text-right">

                                    <button
                                        type="button"
                                        @click="openCoreActivityDetails('event', event)"
                                        class="h-8 rounded-lg border border-gray-200 bg-white px-3 text-[9px] font-semibold text-gray-600 transition hover:border-blue-200 hover:text-[#1d54e2]"
                                    >
                                        Open
                                    </button>

                                </td>

                            </tr>

                        </template>


                        <!-- Empty state -->
                        <template x-if="filteredEventsList.length === 0">

                            <tr>

                                <td colspan="11" class="px-6 py-14 text-center">

                                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <i class="far fa-calendar-alt"></i>
                                    </div>

                                    <div class="mt-3 text-sm font-semibold text-gray-800">
                                        No events found
                                    </div>

                                    <div class="mt-1 text-xs text-gray-400">
                                        Try adjusting the event filters or create a new event.
                                    </div>

                                </td>

                            </tr>

                        </template>

                    </tbody>

                </table>

            </div>


            <!-- Footer -->
            <div class="flex flex-col gap-2 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">

                <div
                    class="text-xs font-medium text-slate-500"
                    x-text="filteredEventsList.length + ' event(s)'"
                ></div>

                <div class="text-[10px] text-slate-400">
                    Event actual duration can contribute to Hours Rendered
                </div>

            </div>

        </div>

    </div>

</div>

</div>

<!-- =========================================================
     CALLS TAB CONTENT
========================================================= -->
<div
    x-show="activeTab === 'call'"
    x-cloak
    style="display: none;"
    class="p-4"
>

    <!-- =====================================================
         CALLS HEADER
    ====================================================== -->
    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

        <div>
            <h2 class="text-xl font-bold tracking-tight text-gray-900">
                Calls
            </h2>

            <p class="mt-1 text-xs text-gray-500">
                Log, track, document, and follow up client and internal calls.
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">

<button
                type="button"
                @click="showExportModal = true"
                class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-700 transition hover:border-blue-300 hover:text-[#1d54e2] focus:outline-none focus:ring-2 focus:ring-blue-100"
            >
                <i class="fas fa-ellipsis-v text-[10px]"></i>
                Reports
            </button>

            <button
                type="button"
                @click="
                    showCallModal = true;
                    newCall.startTime = new Date().toISOString().slice(0, 10);
                    newCall.startHour = new Date().toTimeString().slice(0, 5);
                "
                class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-700 transition hover:border-blue-300 hover:text-[#1d54e2] focus:outline-none focus:ring-2 focus:ring-blue-100"
            >
                Quick Log Call
            </button>

            <button
                type="button"
                @click="showCallModal = true"
                class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#1d54e2] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1541b0] focus:outline-none focus:ring-2 focus:ring-blue-100"
            >
                <i class="fas fa-plus text-[10px]"></i>
                New Call
            </button>

        </div>
    </div>


    <!-- =====================================================
         CALLS CARD
    ====================================================== -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <!-- Filters -->
        <div class="flex flex-col gap-4 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between">

            <!-- View Tabs -->
            <div class="inline-flex w-fit shrink-0 rounded-lg bg-gray-100 p-1">

                <button
                    type="button"
                    @click="callViewTab = 'all'; currentPage = 1"
                    :class="callViewTab === 'all'
                        ? 'bg-white text-[#1d54e2] shadow-sm'
                        : 'text-gray-500 hover:text-gray-800'"
                    class="rounded-md px-3 py-2 text-[11px] font-semibold transition"
                >
                    All Calls
                </button>

                <button
                    type="button"
                    @click="callViewTab = 'open'; currentPage = 1"
                    :class="callViewTab === 'open'
                        ? 'bg-white text-[#1d54e2] shadow-sm'
                        : 'text-gray-500 hover:text-gray-800'"
                    class="rounded-md px-3 py-2 text-[11px] font-semibold transition"
                >
                    Open
                </button>

                <button
                    type="button"
                    @click="callViewTab = 'completed'; currentPage = 1"
                    :class="callViewTab === 'completed'
                        ? 'bg-white text-[#1d54e2] shadow-sm'
                        : 'text-gray-500 hover:text-gray-800'"
                    class="rounded-md px-3 py-2 text-[11px] font-semibold transition"
                >
                    Completed
                </button>

            </div>


            <!-- Search / Filters -->
            <div class="grid w-full grid-cols-1 gap-3 md:grid-cols-3 lg:max-w-[820px]">

                <div class="relative">

                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-300"></i>

                    <input
                        type="text"
                        x-model.debounce.300ms="callSearch"
                        @input="currentPage = 1"
                        placeholder="Search calls"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-3 text-xs text-gray-700 outline-none placeholder:text-gray-400 transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >

                </div>

                <select
                    x-model="callDirectionFilter"
                    @change="currentPage = 1"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="all">All Directions</option>
                    <option value="Inbound">Incoming</option>
                    <option value="Outbound">Outgoing</option>
                </select>

                <select
                    x-model="callStatusFilter"
                    @change="currentPage = 1"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="all">All Statuses</option>
                    <option value="Open">Open</option>
                    <option value="Completed">Completed</option>
                </select>

            </div>
        </div>


        <!-- =================================================
             CALLS TABLE
        ================================================== -->
        <div class="overflow-x-auto">

            <table class="w-full min-w-[1280px] table-auto text-left">

                <thead>

                    <tr class="border-b border-slate-200 bg-slate-50">

                                                <th class="w-12 px-4 py-3 text-left">
                            <input
                                type="checkbox"
                                class="call-select-all h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                aria-label="Select all calls"
                            >
                        </th>
<th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Call
                        </th>

                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Related Account
                        </th>

                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Contact / Other Side
                        </th>

                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Direction
                        </th>

                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Date / Time
                        </th>

                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Actual
                        </th>

                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Status
                        </th>

                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Outcome
                        </th>

                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Follow-Up
                        </th>

                        <th class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-100">

                    <template
                        x-for="call in filteredCallsList.slice((currentPage - 1) * perPage, currentPage * perPage)"
                        :key="call.id"
                    >

                        <tr class="group bg-white transition hover:bg-slate-50/70">

                            <!-- Call -->
                                                        <td class="w-12 px-4 py-4">
                                <input
                                    type="checkbox"
                                    class="call-row-checkbox h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                    aria-label="Select call"
                                >
                            </td>
<td class="px-3 py-3">

                                <button
                                    type="button"
                                    @click="openCoreActivityDetails('call', call)"
                                    class="block max-w-[230px] text-left"
                                >

                                    <div
                                        class="truncate text-xs font-semibold text-[#1d54e2] hover:underline"
                                        x-text="call.title || call.purpose || call.to || 'Call'"
                                    ></div>

                                    <div
                                        class="mt-1 truncate text-[10px] text-slate-400"
                                        x-text="call.category || call.purpose || call.type || 'Call activity'"
                                    ></div>

                                </button>

                            </td>


                            <!-- Related Account -->
                            <td
                                class="px-3 py-3 text-[10px] text-gray-700"
                                x-text="call.related_account || call.related_to || '-'"
                            ></td>


                            <!-- Contact / Other Side -->
                            <td class="px-3 py-3">

                                <div
                                    class="max-w-[180px] truncate text-[10px] font-medium text-gray-700"
                                    x-text="
                                        call.contact ||
                                        (call.type === 'Inbound' ? call.from : call.to) ||
                                        call.to ||
                                        call.from ||
                                        '-'
                                    "
                                ></div>

                                <div
                                    class="mt-1 text-[9px] text-gray-400"
                                    x-text="call.call_scope || '1-to-1 / Small Call'"
                                ></div>

                            </td>


                            <!-- Direction -->
                            <td class="px-3 py-3">

                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-[9px] font-semibold"
                                    :class="call.type === 'Inbound'
                                        ? 'bg-slate-100 text-slate-600'
                                        : 'bg-blue-50 text-blue-600'"
                                    x-text="call.type === 'Inbound' ? 'Incoming' : 'Outgoing'"
                                ></span>

                            </td>


                            <!-- Date / Time -->
                            <td class="px-3 py-3">

                                <div
                                    class="text-[10px] font-medium text-gray-700"
                                    x-text="call.start_time || '-'"
                                ></div>

                                <div
                                    class="mt-1 text-[9px] text-gray-400"
                                    x-text="call.start_hour || ''"
                                ></div>

                            </td>


                            <!-- Actual -->
                            <td
                                class="px-3 py-3 text-[10px] text-gray-700"
                                x-text="call.duration || '\u2014'"
                            ></td>


                            <!-- Status -->
                            <td class="px-3 py-3">

                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-[9px] font-semibold"
                                    :class="call.completed
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-blue-50 text-blue-600'"
                                    x-text="call.completed ? 'Completed' : 'Open'"
                                ></span>

                            </td>


                            <!-- Outcome -->
                            <td
                                class="px-3 py-3 text-[10px] text-gray-700"
                                x-text="call.outcome || (call.completed ? 'Resolved' : 'Pending')"
                            ></td>


                            <!-- Follow-up -->
                            <td class="px-3 py-3">

                                <span
                                    class="text-[10px]"
                                    :class="call.follow_up || call.followUp
                                        ? 'font-medium text-[#1d54e2]'
                                        : 'text-gray-500'"
                                    x-text="
                                        call.follow_up_count
                                            ? call.follow_up_count + ' linked'
                                            : (call.follow_up || call.followUp || '\u2014')
                                    "
                                ></span>

                            </td>


                            <!-- Actions -->
                            <td class="px-3 py-3 text-right">

                                <div class="flex items-center justify-end gap-1">

                                    <button
                                        type="button"
                                        @click="openCoreActivityDetails('call', call)"
                                        class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-[10px] font-semibold text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-[#1d54e2]"
                                    >
                                        Open
                                    </button>

                                    <button
                                        type="button"
                                        @click="editCall(call)"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-blue-50 hover:text-[#1d54e2]"
                                        title="Edit call"
                                    >
                                        <i class="fas fa-pencil-alt text-[10px]"></i>
                                    </button>

                                </div>

                            </td>

                        </tr>

                    </template>


                    <!-- Empty State -->
                    <template x-if="filteredCallsList.length === 0">

                        <tr>

                            <td colspan="11" class="px-6 py-14 text-center">

                                <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                    <i class="fas fa-phone-alt"></i>
                                </div>

                                <div class="mt-3 text-sm font-semibold text-gray-800">
                                    No calls found
                                </div>

                                <div class="mt-1 text-xs text-gray-400">
                                    Try adjusting the call filters or log a new call.
                                </div>

                            </td>

                        </tr>

                    </template>

                </tbody>

            </table>

        </div>


        <!-- =================================================
             FOOTER
        ================================================== -->
        <div class="flex flex-col gap-2 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">

            <div
                class="text-xs font-medium text-slate-500"
                x-text="filteredCallsList.length + ' call(s)'"
            ></div>

            <div class="text-[10px] text-slate-400">
                Actual duration contributes to Hours Rendered
            </div>

        </div>

    </div>

</div>
</div>

<!-- =========================================================
     EMAILS TAB CONTENT
========================================================= -->

<div x-show="activeTab === 'emails'" style="display:none;">

    <div class="p-4">

        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">
                    Emails
                </h1>

                <p class="mt-1 text-xs text-gray-500">
                    Connected client email conversations with internal teamwork, drafts, related activities and tracking.
                </p>
            </div>


            <div class="flex flex-wrap items-center justify-end gap-2">

                <div class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700">
                    <span x-text="emailMailboxConnection ? ('Gmail: ' + emailMailboxConnection.email_address + ' (' + emailMailboxConnection.connection_status + ')') : 'Gmail: Not Connected'"></span>
                    <span x-show="emailMailboxConnection" class="text-slate-500" x-text="emailMailboxConnection ? (emailMailboxConnection.initial_sync_status === 'capped' ? 'Initial history incomplete; continuing next sync' : (emailMailboxConnection.sync_status === 'continuing' ? 'Sync continuing next run' : (emailMailboxConnection.initial_sync_status === 'complete' ? 'Configured history window imported' : 'History import pending'))) : ''"></span>
                    <button type="button" x-show="!emailMailboxConnection || emailMailboxConnection.connection_status !== 'connected'" @click="connectMyGmail()" class="text-[#1d54e2] hover:underline">Connect My Gmail</button>
                    <button type="button" x-show="emailMailboxConnection && emailMailboxConnection.connection_status === 'connected'" @click="disconnectMyGmail()" class="text-slate-500 hover:text-red-600">Disconnect</button>
                </div>

                <button
                    type="button"
                    @click="openComposeEmail()"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#1d54e2] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1541b0] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    + Compose
                </button>

            </div>

        </div>


        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">


            <div class="flex flex-col gap-4 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between">


                <div class="inline-flex w-fit shrink-0 rounded-lg bg-gray-100 p-1">

                    <button @click="emailTab = 'all'" :class="emailTab === 'all' ? 'bg-white shadow-sm' : ''" class="px-3 py-2 text-xs rounded-md">
                        All Emails
                    </button>

                    <button @click="emailTab = 'mine'" :class="emailTab === 'mine' ? 'bg-white shadow-sm' : ''" class="px-3 py-2 text-xs rounded-md">
                        My Emails
                    </button>

                    <button @click="emailTab = 'incoming'" :class="emailTab === 'incoming' ? 'bg-white shadow-sm' : ''" class="px-3 py-2 text-xs rounded-md">
                        Received
                    </button>

                    <button @click="emailTab = 'outgoing'" :class="emailTab === 'outgoing' ? 'bg-white shadow-sm' : ''" class="px-3 py-2 text-xs rounded-md">
                        Sent
                    </button>

                    <button @click="emailTab = 'unlinked'" :class="emailTab === 'unlinked' ? 'bg-white shadow-sm' : ''" class="px-3 py-2 text-xs rounded-md">
                        Unlinked
                    </button>

                </div>


                <div class="grid w-full grid-cols-1 gap-3 md:grid-cols-3 lg:max-w-[820px]">

                    <input
                        x-model="emailSearch"
                        placeholder="Search emails"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none placeholder:text-gray-400 transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">


                    <select class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option>
                            All Clients
                        </option>
                    </select>


                    <select class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option>
                            All Handling
                        </option>
                    </select>

                </div>


            </div>



            <table class="w-full min-w-[1200px] table-auto text-left">

                <thead>

                    <tr class="border-b border-slate-200 bg-slate-50">

                                                <th class="w-12 px-4 py-3 text-left">
                            <input
                                type="checkbox"
                                class="email-select-all h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                aria-label="Select all emails"
                            >
                        </th>
<th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">SUBJECT</th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">CLIENT / ACCOUNT</th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">CONTACT</th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">FROM / TO</th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">DIRECTION</th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">LAST MESSAGE</th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">ATTACH.</th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">HANDLING</th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">ASSIGNED TO</th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">ACTIONS</th>

                    </tr>

                </thead>


                <tbody>

                    <template x-for="email in filteredEmails" :key="email.id">
                        <tr class="border-b border-gray-100">
                                                        <td class="w-12 px-4 py-4">
                                <input
                                    type="checkbox"
                                    class="email-row-checkbox h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                    aria-label="Select email"
                                >
                            </td>
<td class="px-4 py-3 text-xs font-medium text-gray-900" x-text="email.subject || '(No subject)'"></td>
                            <td class="px-4 py-3 text-xs text-gray-600" x-text="email.company?.company_name || (email.channel === 'Messenger' ? (email.technical_metadata?.messenger_sender_name || 'New Messenger Contact') : 'Unlinked')"></td>
                            <td class="px-4 py-3 text-xs text-gray-600" x-text="email.contact_record ? [email.contact_record.first_name, email.contact_record.last_name].filter(Boolean).join(' ') : '-' "></td>
                            <td class="px-4 py-3 text-xs text-gray-600" x-text="email.direction === 'Incoming' ? email.sender_email : (email.recipients || []).join(', ')"></td>
                            <td class="px-4 py-3 text-xs text-gray-600" x-text="email.direction"></td>
                            <td class="px-4 py-3 text-xs text-gray-600" x-text="formatActivityDate(email.occurred_at)"></td>
                            <td class="px-4 py-3 text-xs text-gray-600" x-text="email.has_attachments ? 'Yes' : 'No'"></td>
                            <td class="px-4 py-3 text-xs text-gray-600" x-text="email.direction === 'Outgoing' ? (email.delivery_status || 'Pending') : email.handling"></td>
                            <td class="px-4 py-3 text-xs text-gray-600" x-text="email.mailbox_owner?.name || email.mailbox_owner?.email || '-'"></td>
                            <td class="px-4 py-3 text-xs">
                                <button type="button" @click="openActivityDetails({ type: 'email', source_id: email.id, ...email })" class="font-medium text-blue-600 hover:text-blue-800">View</button>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filteredEmails.length === 0">

                        <td colspan="10"
                            class="py-20 text-center text-sm text-gray-400">

                            No emails found

                        </td>

                    </tr>

                </tbody>

            </table>


        </div>

    </div>

</div>

<!-- Compose Email Modal -->
<div
    x-show="showComposeEmail"
    x-cloak
    @keydown.escape.window="closeComposeEmail()"
    class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-5 pointer-events-none"
    style="display: none;"
    x-transition:enter="ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-[0.98]"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="ease-in duration-150"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-[0.98]"
>
    <section
        @click.stop
        role="dialog"
        aria-modal="true"
        aria-labelledby="compose-email-title"
        class="pointer-events-auto flex max-h-[calc(100vh-1.5rem)] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl sm:max-h-[calc(100vh-2.5rem)]"
    >
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
            <div>
                <h2 id="compose-email-title" class="text-lg font-bold text-slate-900">Compose Email</h2>
                <p class="mt-1 text-xs text-slate-500">Prepare an email using a connected sending mailbox.</p>
            </div>
            <button
                type="button"
                @click="closeComposeEmail()"
                aria-label="Close compose email"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-800"
            >
                <i class="fas fa-times text-xs"></i>
            </button>
        </header>

        <div class="overflow-y-auto px-5 py-5 sm:px-6">
            <div
                x-show="emailComposeNotice"
                x-text="emailComposeNotice"
                role="status"
                class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            ></div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">Related Account</label>
                    @include('partials.activity-relationships', ['relationshipForm' => 'newEmail'])
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">From Mailbox</label>
                    <input
                        type="email"
                        readonly
                        x-model="newEmail.fromMailbox"
                        :placeholder="emailMailboxOptions.length ? emailMailboxOptions[0] : 'Connect My Gmail first'"
                        class="h-10 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700 outline-none"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">To</label>
                    <input
                        type="text"
                        x-model="newEmail.to"
                        placeholder="recipient@example.com"
                        class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">CC</label>
                    <input
                        type="text"
                        x-model="newEmail.cc"
                        placeholder="Optional"
                        class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">BCC</label>
                    <input
                        type="text"
                        x-model="newEmail.bcc"
                        placeholder="Optional"
                        class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    >
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">Subject</label>
                    <input
                        type="text"
                        x-model="newEmail.subject"
                        placeholder="Email subject"
                        class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    >
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">Message</label>
                    <textarea
                        x-model="newEmail.body"
                        rows="8"
                        placeholder="Write your message"
                        class="w-full resize-y rounded-md border border-slate-200 px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    ></textarea>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">Attachments</label>
                    <input type="file" multiple @change="emailAttachmentFiles = [...$event.target.files]" class="block w-full text-xs text-slate-600">
                </div>
            </div>
        </div>

        <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6">
            <button
                type="button"
                @click="closeComposeEmail()"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Cancel
            </button>
            <button
                type="button"
                @click="attemptEmailCompose('send')"
                class="rounded-lg bg-[#1d54e2] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
                Send Email
            </button>
        </footer>
    </section>
</div>

<!-- =========================================================
     MESSAGES TAB CONTENT
========================================================= -->

<div x-show="activeTab === 'messages'" x-cloak class="bg-white">

    <div class="p-4">

        <!-- Header -->
        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">
                    Messages
                </h1>

                <p class="mt-1 text-xs text-gray-500">
                    Client conversations across messaging channels, with internal teamwork and related activities.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">

<button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-700 transition hover:border-blue-300 hover:text-[#1d54e2] focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    Communication Lines
                </button>

                <button
                    type="button"
                    @click="showNewMessage = true"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#1d54e2] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1541b0] focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    <i class="fas fa-plus mr-2 text-[10px]"></i>
                    New Message
                </button>

            </div>

        </div>


        <!-- Messages Workspace -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <!-- Toolbar -->
            <div class="flex flex-col gap-4 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between">

                <!-- Tabs -->
                <div class="inline-flex w-fit shrink-0 flex-wrap items-center gap-1 rounded-lg bg-gray-100 p-1">

                    <button
                        type="button"
                        @click="messageTab = 'all'"
                        :class="messageTab === 'all'
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'"
                        class="rounded-md px-3 py-2 text-xs font-medium transition"
                    >
                        All Messages
                    </button>

                    <button
                        type="button"
                        @click="messageTab = 'mine'"
                        :class="messageTab === 'mine'
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'"
                        class="rounded-md px-3 py-2 text-xs font-medium transition"
                    >
                        My Messages
                    </button>









                    <button
                        type="button"
                        @click="messageTab = 'unlinked'"
                        :class="messageTab === 'unlinked'
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'"
                        class="rounded-md px-3 py-2 text-xs font-medium transition"
                    >
                        Unlinked
                    </button>

                </div>


                            <div
                x-show="['messenger', 'whatsapp', 'viber', 'sms'].includes(messageTab)"
                x-cloak
                class="mb-4 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3"
            >
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-[#1d54e2] shadow-sm">
                        <i class="fas fa-info-circle text-[11px]"></i>
                    </div>

                    <div>
                        <p
                            class="text-xs font-semibold text-gray-800"
                            x-text="
                                (messageTab === 'viber'
                                    ? 'Viber'
                                    : messageTab.charAt(0).toUpperCase() + messageTab.slice(1))
                                + ' channel'
                            "
                        ></p>

                        <p class="mt-1 text-xs leading-5 text-gray-600">
                            External provider connection is not configured yet.
                            Records shown here are currently stored inside the JK&amp;C application only.
                        </p>
                    </div>
                </div>
            </div>
<!-- Search / Filters -->
                <div class="grid w-full grid-cols-1 gap-3 md:grid-cols-3 lg:max-w-[820px]">

                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-400"></i>

                        <input
                            type="text"
                            x-model="messageSearch"
                            placeholder="Search conversations"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-3 text-xs text-gray-700 outline-none placeholder:text-gray-400 transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <select
                        x-model="messageClientFilter"
                        class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="all">All Clients</option>
                        <template x-for="client in messageClientOptions" :key="'message-client-filter-' + client">
                            <option :value="client" x-text="client"></option>
                        </template>
                    </select>

                    <select
                        x-model="messageHandlingFilter"
                        class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="all">All Handling</option>
                        <option value="open">Open</option>
                        <option value="pending">Pending</option>
                        <option value="resolved">Resolved</option>
                    </select>

                </div>

            </div>


            <!-- Table -->
            <div class="overflow-x-auto">

                <table class="min-w-[1450px] w-full text-left">

                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">

                                                    <th class="w-12 px-4 py-3 text-left">
                            <input
                                type="checkbox"
                                class="message-select-all h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                aria-label="Select all messages"
                            >
                        </th>
<th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                Conversation
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                Client / Account
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                Contact
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                Channel
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                Line / Account Used
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                Last Message
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                Technical
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                Handling
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                Assigned To
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                Actions
                            </th>

                        </tr>
                    </thead>


                    <tbody>

                        <template x-for="conversation in filteredMessages" :key="'message-conversation-' + conversation.id">
                            <tr class="border-b border-gray-100 text-xs text-gray-700">
                                                            <td class="w-12 px-4 py-4">
                                <input
                                    type="checkbox"
                                    class="message-row-checkbox h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                    aria-label="Select message"
                                >
                            </td>
<td class="px-4 py-3 font-medium text-gray-900" x-text="conversation.conversation_title || 'New Conversation'"></td>
                                <td class="px-4 py-3" x-text="conversation.company?.company_name || 'Unlinked'"></td>
                                <td class="px-4 py-3" x-text="messageContactName(conversation.contact) || '-'"></td>
                                <td class="px-4 py-3" x-text="conversation.channel || '-'"></td>
                                <td class="px-4 py-3" x-text="conversation.line_account || '-'"></td>
                                <td class="max-w-[260px] truncate px-4 py-3" x-text="conversation.latest_entry?.body || 'No messages yet'"></td>
                                <td class="px-4 py-3" x-text="messageTechnicalSummary(conversation)"></td>
                                <td class="px-4 py-3" x-text="conversation.handling || 'Open'"></td>
                                <td class="px-4 py-3" x-text="conversation.assigned_user?.name || conversation.assigned_user?.email || 'Unassigned'"></td>
                                <td class="px-4 py-3">
                                    <button type="button" @click="openMessageConversation(conversation)" class="text-xs font-semibold text-[#1d54e2] hover:text-[#1541b0]">View</button>
                                </td>
                            </tr>
                        </template>

                        <!-- Frontend empty state -->
                        <template x-if="!messageLoading && filteredMessages.length === 0">
                            <tr>
                                <td
                                    colspan="10"
                                    class="px-4 py-20 text-center"
                                >
                                    <div x-show="messageRequestError" class="text-sm text-gray-400" x-text="messageRequestError"></div>
                                    <div x-show="!messageRequestError" class="text-sm text-gray-400">
                                        <span
                                            x-text="
                                                ['messenger', 'whatsapp', 'viber', 'sms'].includes(messageTab)
                                                    ? 'No ' + (messageTab === 'viber'
                                                        ? 'Viber'
                                                        : messageTab.charAt(0).toUpperCase() + messageTab.slice(1))
                                                        + ' conversations recorded yet.'
                                                    : 'No conversations found.'
                                            "
                                        ></span>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <template x-if="messageLoading">
                            <tr>
                                <td colspan="10" class="px-4 py-20 text-center text-sm text-gray-400">Loading conversations...</td>
                            </tr>
                        </template>

                    </tbody>

                </table>

            </div>


            <!-- Footer Note -->
            <div class="border-t border-gray-100 px-5 py-3">

                <p class="text-[11px] text-gray-400">
                    One client can have multiple messaging channels under the same Activities history.
                </p>

            </div>

        </div>

    </div>

</div>

<!-- =========================================================
     MEETINGS TAB CONTENT
========================================================= -->
<!-- =========================================================
     NEW MESSAGE MODAL
========================================================= -->
<div
    x-show="showNewMessage"
    x-cloak
    style="display:none;"
    class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-900/40 p-3 sm:p-5"
    @keydown.escape.window="showNewMessage = false"
>
    <div
        @click.stop
        class="w-full max-w-[760px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
    >

        <!-- Header -->
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
            <h3 class="text-[17px] font-semibold text-slate-800">
                New Message
            </h3>

            <button
                type="button"
                @click="showNewMessage = false"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                aria-label="Close"
            >
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="px-5 pb-5">

            <div class="grid grid-cols-1 gap-x-3 gap-y-3 md:grid-cols-2">

                <!-- Related Account -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-500">
                        Related Account
                    </label>

                    @include('partials.activity-relationships', ['relationshipForm' => 'newMessage'])
                </div>

                <!-- Contact -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-500">
                        Contact
                    </label>


                </div>

                <!-- Recipient Number / Account -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-500">
                        Recipient Number / Account
                    </label>

                    <input
                        type="text"
                        x-model="newMessage.recipient"
                        placeholder="0917 XXX XXXX"
                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    >
                </div>

                <!-- Channel -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-500">
                        Channel
                    </label>

                    <select
                        x-model="newMessage.channel"
                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="">Select channel</option>
                        <option value="Viber Business">Viber Business</option>
                        <option value="SMS">SMS</option>
                        <option value="WhatsApp">WhatsApp</option>
                        <option value="Messenger">Messenger</option>
                    </select>
                </div>

                <!-- Communication Line / Account -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-500">
                        Communication Line / Account
                    </label>

                    <select
                        x-model="newMessage.lineAccount"
                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="">Select communication line</option>
                        <option value="JK&C Viber Business">JK&C Viber Business</option>
                        <option value="JK&C SMS Main Line">JK&C SMS Main Line</option>
                        <option value="JK&C WhatsApp">JK&C WhatsApp</option>
                        <option value="JK&C Messenger">JK&C Messenger</option>
                    </select>
                </div>

                <!-- Assigned To -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-500">
                        Assigned To
                    </label>

                    <select
                        x-model="newMessage.assignedTo"
                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="">Select employee</option>

                        <template x-for="user in messageUserRecords" :key="'message-user-' + user.id">
                            <option :value="user.email || user.name" x-text="user.name ? user.name + (user.email ? ' - ' + user.email : '') : user.email"></option>
                        </template>
                    </select>
                </div>

                <!-- Message -->
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-500">
                        Message
                    </label>

                    <textarea
                        rows="6"
                        x-model="newMessage.body"
                        placeholder="Good afternoon. This is John Kelly & Company. We are following up regarding your documents."
                        class="w-full resize-none rounded-md border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    ></textarea>
                </div>

            </div>

            <!-- Footer -->
            <p x-show="messageRequestError" x-text="messageRequestError" class="mt-3 text-xs text-red-600"></p>
            <div class="mt-4 flex items-center justify-end gap-2">

                <button
                    type="button"
                    @click="showNewMessage = false"
                    class="h-9 rounded-md border border-slate-200 bg-white px-4 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    class="h-9 rounded-md border border-slate-200 bg-white px-4 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="submitNewMessage('draft')"
                    :disabled="messageSaving"
                >
                    Save Draft
                </button>

                <button
                    type="button"
                    class="h-9 rounded-md bg-[#3157e8] px-4 text-xs font-semibold text-white transition hover:bg-[#2446c8]"
                    @click="submitNewMessage('send')"
                    :disabled="messageSaving"
                >
                    Send Message
                </button>

            </div>

        </div>

    </div>
</div>
<!-- =========================================================
     MESSAGE CONVERSATION DETAILS
========================================================= -->
<!-- =========================================================
     OFFICIAL BUSINESS TAB CONTENT
========================================================= -->
<div
    x-show="activeTab === 'official-business'"
    x-cloak
    class="bg-[#f8fafc]"
>
    <!-- ALL OFFICIAL BUSINESS -->
    <div
        x-show="officialBusinessTab === 'all'"
        x-cloak
        class="p-4"
    >

        <!-- Header -->
        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">
                    All Official Business
                </h1>

                <p class="mt-1 text-xs text-gray-500">
                    Management view for monitoring, filtering, opening, and reporting all O.B. records.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">

<button
                    type="button"
                    @click="showExportModal = true" class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-700 transition hover:border-blue-300 hover:text-[#1d54e2] focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    Reports
                </button>

                <button
                    type="button"
                    @click="openOfficialBusinessWorkspace(null, 'create')"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#1d54e2] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1541b0] focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    <i class="fas fa-plus mr-2 text-[10px]"></i>
                    Add New
                </button>

            </div>

        </div>


        <!-- Workspace -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <!-- Filters -->
            <div class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 md:grid-cols-2 xl:grid-cols-7">

                <!-- Search -->
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-slate-400"></i>

                    <input
                        type="text"
                        x-model="obSearch"
                        placeholder="Search O.B., employee, client, destination"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-3 text-xs text-gray-700 outline-none placeholder:text-gray-400 transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                </div>


                <!-- Status -->
                <select
                    x-model="obStatusFilter"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="all">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Ready for Travel">Ready for Travel</option>
                    <option value="Rejected">Rejected</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Returned">Returned</option>
                    <option value="For Completion">For Completion</option>
                    <option value="Completed">Completed</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Draft">Draft</option>
                    <option value="Reverted">Reverted</option>
                    <option value="Cancelled">Cancelled</option>
                </select>


                <!-- Employee -->
                <select
                    x-model="obEmployeeFilter"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="all">All Employees</option>
                    <template x-for="employee in officialBusinessEmployees" :key="'ob-employee-' + employee">
                        <option :value="employee" x-text="employee"></option>
                    </template>
                </select>


                <!-- Client -->
                <select
                    x-model="obClientFilter"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="all">All Clients / Internal</option>
                    <template x-for="client in officialBusinessClients" :key="'ob-client-' + client">
                        <option :value="client" x-text="client"></option>
                    </template>
                </select>

                <select x-model="obDepartmentFilter" aria-label="O.B. department"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700">
                    <option value="all">All Departments</option>
                    <template x-for="department in officialBusinessDepartments" :key="department">
                        <option :value="department" x-text="department"></option>
                    </template>
                </select>


                <!-- OB Type -->
                <select
                    x-model="obTypeFilter"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="all">All O.B. Types</option>
                    <option value="One Client">One Client</option>
                    <option value="Multiple Clients">Multiple Clients</option>
                    <option value="Internal / JK&C">Internal / JK&C</option>
                </select>


                <!-- Date From -->
                <input
                    type="date"
                    x-model="obDateFrom"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >


                <!-- Date To -->
                <input
                    type="date"
                    x-model="obDateTo"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >

            </div>


            <!-- Table -->
            <div class="overflow-x-auto">

                <table class="w-full min-w-[1500px] table-auto text-left">

                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">

                                                        <th class="w-12 px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    class="official-business-select-all h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                    aria-label="Select all Official Business records"
                                >
                            </th>
<th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                OBF Ref No.
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Employee
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                O.B. Type
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Client(s)
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Destination / Stops
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Planned Departure
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Expected Return
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Status
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Actions
                            </th>

                        </tr>
                    </thead>


                    <tbody class="divide-y divide-slate-100">

                        <template
                            x-for="record in filteredOfficialBusinessRecords()"
                            :key="record.ref"
                        >
                            <tr
                                @click="openOfficialBusinessDetails(record)"
                                class="cursor-pointer transition hover:bg-slate-50/70"
                            >

                                <!-- Ref -->
                                                                <td
                                    class="w-12 px-4 py-4"
                                    @click.stop
                                >
                                    <input
                                        type="checkbox"
                                        class="official-business-row-checkbox h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                        aria-label="Select Official Business record"
                                    >
                                </td>
<td class="whitespace-nowrap px-4 py-4">
                                    <button
                                        type="button"
                                        @click.stop="openOfficialBusinessDetails(record)"
                                        class="text-xs font-semibold text-[#1d54e2] hover:underline"
                                        x-text="record.ref"
                                    ></button>
                                </td>


                                <!-- Employee -->
                                <td
                                    class="whitespace-nowrap px-4 py-4 text-xs text-slate-700"
                                    x-text="record.employee"
                                ></td>


                                <!-- Type -->
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span
                                        class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-semibold text-slate-600"
                                        x-text="record.type"
                                    ></span>
                                </td>


                                <!-- Clients -->
                                <td
                                    class="max-w-[310px] px-3 py-3.5 text-[11px] leading-5 text-slate-700"
                                    x-text="record.clients"
                                ></td>


                                <!-- Destination -->
                                <td
                                    class="min-w-[330px] px-3 py-3.5 text-[11px] leading-5 text-slate-700"
                                    x-text="record.destination"
                                ></td>


                                <!-- Departure -->
                                <td
                                    class="whitespace-nowrap px-4 py-4 text-xs text-slate-700"
                                    x-text="record.departure"
                                ></td>


                                <!-- Expected Return -->
                                <td
                                    class="whitespace-nowrap px-4 py-4 text-xs text-slate-700"
                                    x-text="record.expectedReturn"
                                ></td>


                                <!-- Status -->
                                <td class="whitespace-nowrap px-4 py-4">

                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-[9px] font-semibold"
                                        :class="{
                                            'bg-blue-50 text-blue-600': ['Approved', 'Ready for Travel'].includes(record.status),
                                            'bg-amber-50 text-amber-600': ['Returned', 'For Completion'].includes(record.status),
                                            'bg-emerald-50 text-emerald-600': record.status === 'Completed',
                                            'bg-violet-50 text-violet-600': ['In Progress', 'Ongoing'].includes(record.status),
                                            'bg-cyan-50 text-cyan-600': record.status === 'Submitted',
                                            'bg-rose-50 text-rose-600': record.status === 'Rejected',
                                            'bg-slate-100 text-slate-600': record.status === 'Draft',
                                            'bg-orange-50 text-orange-600': record.status === 'Reverted',
                                            'bg-slate-100 text-slate-500': record.status === 'Cancelled'
                                        }"
                                        x-text="record.status"
                                    ></span>

                                </td>


                                <!-- Actions -->
                                <td class="whitespace-nowrap px-4 py-4">

                                    <button
                                        type="button"
                                        @click.stop="openOfficialBusinessDetails(record)"
                                        class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-[10px] font-medium text-slate-600 transition hover:border-blue-200 hover:text-[#1d54e2]"
                                    >
                                        View
                                    </button>

                                    <button
                                        x-show="Number(record.workflowVersion || record.workflow_version || 1) >= 3 && record.is_client_travel && (record.trip_tickets || []).length"
                                        type="button"
                                        @click.stop="openOfficialBusinessCompletion(record)"
                                        class="ml-1 h-8 rounded-lg border border-blue-200 bg-blue-50 px-3 text-[10px] font-medium text-blue-700 transition hover:bg-blue-100"
                                        x-text="`Trip Tickets (${record.trip_tickets?.length || 0})`"
                                    ></button>

                                </td>

                            </tr>
                        </template>


                        <!-- Empty -->
                        <tr x-show="filteredOfficialBusinessRecords().length === 0">
                            <td
                                colspan="9"
                                class="px-4 py-16 text-center text-xs text-slate-400"
                            >
                                No official business records found.
                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>


            <!-- Footer -->
            <div class="border-t border-slate-100 px-4 py-3">

                <span
                    class="text-[10px] text-slate-500"
                    x-text="filteredOfficialBusinessRecords().length + ' O.B. record(s)'"
                ></span>

            </div>

        </div>

    </div>

    <!-- MY O.B. -->
    <div
        x-show="officialBusinessTab === 'mine'"
        x-cloak
        class="p-4"
    >

        <!-- Header -->
        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">
                    My Official Business
                </h1>

                <p class="mt-1 text-xs text-gray-500">
                    Your own O.B. drafts, submitted records, travel status, returned trips, and completed records.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">

<button
                    type="button"
                    @click="showExportModal = true" class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-700 transition hover:border-blue-300 hover:text-[#1d54e2] focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    Reports
                </button>

                <button
                    type="button"
                    @click="openOfficialBusinessWorkspace(null, 'create')"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#1d54e2] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1541b0] focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    <i class="fas fa-plus mr-2 text-[10px]"></i>
                    Add New
                </button>

            </div>

        </div>


        <!-- Workspace -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <!-- Status Tabs + Filters -->
            <div class="border-b border-slate-200">

                <div class="flex flex-col gap-4 p-4 xl:flex-row xl:items-center xl:justify-between">

                    <!-- Status Tabs -->
                    <div class="flex flex-wrap items-center gap-1 rounded-lg bg-gray-100 p-1">

                        <template
                            x-for="tab in [
                                { key: 'all', label: 'All' },
                                { key: 'Pending', label: 'Pending' },
                                { key: 'Draft', label: 'Draft' },
                                { key: 'Submitted', label: 'Submitted' },
                                { key: 'Approved', label: 'Approved' },
                                { key: 'Ready for Travel', label: 'Ready for Travel' },
                                { key: 'Reverted', label: 'Reverted' },
                                { key: 'Cancelled', label: 'Cancelled' },
                                { key: 'In Progress', label: 'In Progress' },
                                { key: 'Ongoing', label: 'Ongoing' },
                                { key: 'Returned', label: 'Returned' },
                                { key: 'For Completion', label: 'For Completion' },
                                { key: 'Completed', label: 'Completed' },
                                { key: 'Rejected', label: 'Rejected' }
                            ]"
                            :key="'my-ob-tab-' + tab.key"
                        >
                            <button
                                type="button"
                                @click="myObStatusTab = tab.key"
                                :class="myObStatusTab === tab.key
                                    ? 'border-blue-200 bg-blue-50 text-[#1d54e2]'
                                    : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-700'"
                                class="h-9 rounded-md border px-3 text-[10px] font-semibold transition"
                                x-text="tab.label"
                            ></button>
                        </template>

                    </div>


                    <!-- Filters -->
                    <div class="grid w-full grid-cols-1 gap-3 md:grid-cols-2 xl:w-auto xl:grid-cols-5">

                        <!-- Search -->
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-slate-400"></i>

                            <input
                                type="text"
                                x-model="myObSearch"
                                placeholder="Search O.B., employee, client, destination"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-3 text-xs text-gray-700 outline-none placeholder:text-gray-400 transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                        </div>


                        <!-- Status -->
                        <select
                            x-model="myObStatusFilter"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="all">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Draft">Draft</option>
                            <option value="Submitted">Submitted</option>
                            <option value="Approved">Approved</option>
                            <option value="Ready for Travel">Ready for Travel</option>
                            <option value="Reverted">Reverted</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Returned">Returned</option>
                            <option value="For Completion">For Completion</option>
                            <option value="Completed">Completed</option>
                            <option value="Rejected">Rejected</option>
                        </select>


                        <!-- Client -->
                        <select
                            x-model="myObClientFilter"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="all">All Clients / Internal</option>
                            <template x-for="client in officialBusinessClients" :key="'my-ob-client-' + client">
                                <option :value="client" x-text="client"></option>
                            </template>
                        </select>


                        <!-- O.B. Type -->
                        <select
                            x-model="myObTypeFilter"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="all">All O.B. Types</option>
                            <option value="One Client">One Client</option>
                            <option value="Multiple Clients">Multiple Clients</option>
                            <option value="Internal / JK&C">Internal / JK&C</option>
                        </select>


                        <!-- Date From -->
                        <input
                            type="date"
                            x-model="myObDateFrom"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >


                        <!-- Date To -->
                        <input
                            type="date"
                            x-model="myObDateTo"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >

                    </div>

                </div>

            </div>


            <!-- Table -->
            <div class="overflow-x-auto">

                <table class="w-full min-w-[1500px] table-auto text-left">

                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">

                                                        <th class="w-12 px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    class="my-ob-select-all h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                    aria-label="Select all My Official Business records"
                                >
                            </th>
<th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                OBF Ref No.
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Employee
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                O.B. Type
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Client(s)
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Destination / Stops
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Planned Departure
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Expected Return
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Status
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Actions
                            </th>

                        </tr>
                    </thead>


                    <tbody class="divide-y divide-slate-100">

                        <template
                            x-for="record in filteredMyOfficialBusinessRecords()"
                            :key="'my-ob-' + record.ref"
                        >
                            <tr
                                @click="openOfficialBusinessDetails(record)"
                                class="cursor-pointer transition hover:bg-slate-50/70"
                            >

                                <!-- Ref -->
                                                                <td class="w-12 px-4 py-4" @click.stop>
                                    <input
                                        type="checkbox"
                                        class="my-ob-row-checkbox h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                        aria-label="Select My Official Business record"
                                    >
                                </td>
<td class="whitespace-nowrap px-4 py-4">
                                    <button
                                        type="button"
                                        @click.stop="openOfficialBusinessDetails(record)"
                                        class="text-xs font-semibold text-[#1d54e2] hover:underline"
                                        x-text="record.ref"
                                    ></button>
                                </td>


                                <!-- Employee -->
                                <td
                                    class="whitespace-nowrap px-4 py-4 text-xs text-slate-700"
                                    x-text="record.employee"
                                ></td>


                                <!-- Type -->
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span
                                        class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-semibold text-slate-600"
                                        x-text="record.type"
                                    ></span>
                                </td>


                                <!-- Clients -->
                                <td
                                    class="max-w-[310px] px-3 py-3.5 text-[11px] leading-5 text-slate-700"
                                    x-text="record.clients"
                                ></td>


                                <!-- Destination -->
                                <td
                                    class="min-w-[330px] px-3 py-3.5 text-[11px] leading-5 text-slate-700"
                                    x-text="record.destination"
                                ></td>


                                <!-- Departure -->
                                <td
                                    class="whitespace-nowrap px-4 py-4 text-xs text-slate-700"
                                    x-text="record.departure"
                                ></td>


                                <!-- Expected Return -->
                                <td
                                    class="whitespace-nowrap px-4 py-4 text-xs text-slate-700"
                                    x-text="record.expectedReturn"
                                ></td>


                                <!-- Status -->
                                <td class="whitespace-nowrap px-4 py-4">

                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-[9px] font-semibold"
                                        :class="{
                                            'bg-blue-50 text-blue-600': ['Approved', 'Ready for Travel'].includes(record.status),
                                            'bg-emerald-50 text-emerald-600': record.status === 'Completed',
                                            'bg-slate-100 text-slate-600': record.status === 'Draft',
                                            'bg-orange-50 text-orange-600': record.status === 'Reverted',
                                            'bg-slate-100 text-slate-500': record.status === 'Cancelled',
                                            'bg-violet-50 text-violet-600': ['In Progress', 'Ongoing'].includes(record.status),
                                            'bg-amber-50 text-amber-600': ['Returned', 'For Completion'].includes(record.status),
                                            'bg-cyan-50 text-cyan-600': record.status === 'Submitted'
                                        }"
                                        x-text="record.status"
                                    ></span>

                                </td>


                                <!-- Actions -->
                                <td class="whitespace-nowrap px-4 py-4">

                                    <div class="flex items-center gap-1">

                                        <button
                                            type="button"
                                            @click.stop="openOfficialBusinessDetails(record)"
                                            class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-[10px] font-medium text-slate-600 transition hover:border-blue-200 hover:text-[#1d54e2]"
                                        >
                                            View
                                        </button>

                                        <button
                                            x-show="Number(record.workflowVersion || record.workflow_version || 1) >= 3 && record.is_client_travel && (record.trip_tickets || []).length"
                                            type="button"
                                            @click.stop="openOfficialBusinessCompletion(record)"
                                            class="h-8 rounded-lg border border-blue-200 bg-blue-50 px-3 text-[10px] font-medium text-blue-700 transition hover:bg-blue-100"
                                            x-text="`Trip Tickets (${record.trip_tickets?.length || 0})`"
                                        ></button>

                                        <button
                                            x-show="record.can_delete && ['Draft', 'Reverted'].includes(record.status) && !record.deletion_request_pending"
                                            type="button"
                                            @click.stop="requestOfficialBusinessDeletion(record)"
                                            class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-[10px] font-medium text-slate-600 transition hover:border-red-200 hover:text-red-600"
                                        >
                                            Request Delete
                                        </button>

                                    </div>

                                </td>

                            </tr>
                        </template>


                        <!-- Empty -->
                        <tr x-show="filteredMyOfficialBusinessRecords().length === 0">
                            <td
                                colspan="10"
                                class="px-4 py-16 text-center text-xs text-slate-400"
                            >
                                No official business records found.
                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>


            <!-- Footer -->
            <div class="border-t border-slate-100 px-4 py-3">

                <span
                    class="text-[10px] text-slate-500"
                    x-text="filteredMyOfficialBusinessRecords().length + ' O.B. record(s)'"
                ></span>

            </div>

        </div>

    </div>


    <!-- TRAVEL DESK -->
    <div
        x-show="officialBusinessTab === 'travel-desk'"
        x-cloak
        class="p-4"
    >

        <!-- Header -->
        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <h1 class="text-xl font-bold tracking-tight text-gray-900">
                Travel Desk
            </h1>

            <p class="mt-1 text-xs text-gray-500">
                Movement control only &mdash; record when an approved traveler departs and when the traveler returns.
            </p>
</div>


        <!-- Summary Cards -->
        <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">

            <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    Awaiting Readiness
                </div>

                <div class="mt-2 text-xl font-bold tracking-tight text-slate-900">
                    <span x-text="travelDeskRecords.filter(record => record.movement === 'Awaiting Readiness').length"></span>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    Ready to Depart
                </div>

                <div class="mt-2 text-xl font-bold tracking-tight text-slate-900">
                    <span x-text="travelDeskRecords.filter(record => record.movement === 'Ready').length"></span>
                </div>
            </div>


            <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    Currently Out
                </div>

                <div class="mt-2 text-xl font-bold tracking-tight text-slate-900">
                    <span x-text="travelDeskRecords.filter(record => record.movement === 'Ongoing').length"></span>
                </div>
            </div>


            <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    Returned
                </div>

                <div class="mt-2 text-xl font-bold tracking-tight text-slate-900">
                    <span x-text="travelDeskRecords.filter(record => record.movement === 'Returned').length"></span>
                </div>
            </div>


            <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    Overdue Return
                </div>

                <div class="mt-2 text-xl font-bold tracking-tight text-slate-900">
                    <span x-text="travelDeskRecords.filter(record => record.movement === 'Overdue').length"></span>
                </div>
            </div>

        </div>


        <!-- Travel Desk Workspace -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <!-- Toolbar -->
            <div class="flex flex-col gap-4 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">

                <!-- Movement Tabs -->
                <div class="flex w-fit flex-wrap items-center gap-1 rounded-lg bg-gray-100 p-1">

                    <button
                        type="button"
                        @click="travelDeskTab = 'awaiting readiness'"
                        :class="travelDeskTab === 'awaiting readiness'
                            ? 'border-blue-200 bg-blue-50 text-[#1d54e2]'
                            : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                        class="h-9 rounded-md border px-3 text-[10px] font-semibold transition"
                    >
                        Awaiting Readiness
                    </button>

                    <button
                        type="button"
                        @click="travelDeskTab = 'ready'"
                        :class="travelDeskTab === 'ready'
                            ? 'border-blue-200 bg-blue-50 text-[#1d54e2]'
                            : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                        class="h-9 rounded-md border px-3 text-[10px] font-semibold transition"
                    >
                        Ready to Depart
                    </button>


                    <button
                        type="button"
                        @click="travelDeskTab = 'ongoing'"
                        :class="travelDeskTab === 'ongoing'
                            ? 'border-blue-200 bg-blue-50 text-[#1d54e2]'
                            : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                        class="h-9 rounded-md border px-3 text-[10px] font-semibold transition"
                    >
                        Ongoing
                    </button>


                    <button
                        type="button"
                        @click="travelDeskTab = 'returned'"
                        :class="travelDeskTab === 'returned'
                            ? 'border-blue-200 bg-blue-50 text-[#1d54e2]'
                            : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                        class="h-9 rounded-md border px-3 text-[10px] font-semibold transition"
                    >
                        Returned
                    </button>


                    <button
                        type="button"
                        @click="travelDeskTab = 'overdue'"
                        :class="travelDeskTab === 'overdue'
                            ? 'border-blue-200 bg-blue-50 text-[#1d54e2]'
                            : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                        class="h-9 rounded-md border px-3 text-[10px] font-semibold transition"
                    >
                        Overdue Return
                    </button>


                    <button
                        type="button"
                        @click="travelDeskTab = 'all'"
                        :class="travelDeskTab === 'all'
                            ? 'border-blue-200 bg-blue-50 text-[#1d54e2]'
                            : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                        class="h-9 rounded-md border px-3 text-[10px] font-semibold transition"
                    >
                        All
                    </button>

                </div>


                <!-- Search -->
                <div class="relative">

                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[9px] text-slate-400"></i>

                    <input
                        type="text"
                        x-model="travelDeskSearch"
                        placeholder="Search traveler, OBF, client, destination"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-3 text-xs text-gray-700 outline-none placeholder:text-gray-400 transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 sm:w-[320px]"
                    >

                </div>

            </div>


            <!-- Table -->
            <div class="overflow-x-auto">

                <table class="w-full min-w-[1500px] table-auto text-left">

                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">

                                                        <th class="w-12 px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    class="travel-desk-select-all h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                    aria-label="Select all Travel Desk records"
                                >
                            </th>
<th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                OBF Ref
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Traveler
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Client / Internal
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Destination
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Planned Departure
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Expected Return
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Actual Departure
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Actual Return
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Movement
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Action
                            </th>

                        </tr>
                    </thead>


                    <tbody>

                        <template
                            x-for="record in filteredTravelDeskRecords()"
                            :key="'travel-' + record.ref"
                        >
                            <tr class="border-b border-slate-100 transition last:border-b-0 hover:bg-slate-50/70">

                                <!-- OBF REF -->
                                                                <td class="w-12 px-4 py-4">
                                    <input
                                        type="checkbox"
                                        class="travel-desk-row-checkbox h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                        aria-label="Select Travel Desk record"
                                    >
                                </td>
<td class="whitespace-nowrap px-4 py-4">
                                    <span
                                        class="text-xs font-semibold text-slate-800"
                                        x-text="record.ref"
                                    ></span>
                                </td>


                                <!-- Traveler -->
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span
                                        class="text-xs text-slate-800"
                                        x-text="record.traveler"
                                    ></span>
                                </td>


                                <!-- Client / Internal -->
                                <td class="px-4 py-4">
                                    <span
                                        class="text-xs text-slate-700"
                                        x-text="record.client"
                                    ></span>
                                </td>


                                <!-- Destination -->
                                <td class="px-4 py-4">
                                    <span
                                        class="text-xs text-slate-700"
                                        x-text="record.destination"
                                    ></span>
                                </td>


                                <!-- Planned Departure -->
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span
                                        class="text-xs text-slate-700"
                                        x-text="record.plannedDeparture"
                                    ></span>
                                </td>


                                <!-- Expected Return -->
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span
                                        class="text-xs text-slate-700"
                                        x-text="record.expectedReturn"
                                    ></span>
                                </td>


                                <!-- Actual Departure -->
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span
                                        class="text-xs text-slate-700"
                                        x-text="record.actualDeparture || record.actual_departure ? formatTaskDateTime(record.actualDeparture || record.actual_departure) : '\u2014'"
                                    ></span>
                                </td>


                                <!-- Actual Return -->
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span
                                        class="text-xs text-slate-700"
                                        x-text="record.actualReturn || record.actual_return ? formatTaskDateTime(record.actualReturn || record.actual_return) : '\u2014'"
                                    ></span>
                                </td>


                                <!-- Movement -->
                                <td class="whitespace-nowrap px-4 py-4">

                                    <span
                                        class="text-[10px] font-semibold"
                                        :class="{
                                            'text-cyan-600': record.movement === 'Awaiting Readiness',
                                            'text-blue-600': record.movement === 'Ready',
                                            'text-violet-600': record.movement === 'Ongoing',
                                            'text-emerald-600': record.movement === 'Returned',
                                            'text-red-600': record.movement === 'Overdue'
                                        }"
                                        x-text="record.movement"
                                    ></span>

                                </td>


                                <!-- Action -->
                                <td class="whitespace-nowrap px-4 py-4">

                                    <button
                                        x-show="(record.movement === 'Awaiting Readiness' || record.movement === 'Not Started') && (record.canControlTravel || record.can_control_travel || officialBusinessCapabilities?.travel_movement)"
                                        type="button"
                                        @click="transitionOfficialBusinessStatus($data, record, 'ready')"
                                        class="h-8 min-w-[120px] rounded-lg bg-[#1d54e2] px-4 text-[10px] font-semibold text-white transition hover:bg-[#1541b0]"
                                    >
                                        Mark Ready
                                    </button>

                                    <button
                                        x-show="record.movement === 'Ready' && (record.canControlTravel || record.can_control_travel || officialBusinessCapabilities?.travel_movement)"
                                        type="button"
                                        @click="departTravelDeskRecord(record)"
                                        class="h-8 min-w-[92px] rounded-lg bg-[#1d54e2] px-4 text-[10px] font-semibold text-white transition hover:bg-[#1541b0]"
                                    >
                                        Depart
                                    </button>


                                    <button
                                        x-show="(record.movement === 'Departed' || record.movement === 'Ongoing' || record.movement === 'Overdue') && (record.canControlTravel || record.can_control_travel || officialBusinessCapabilities?.travel_movement)"
                                        type="button"
                                        @click="returnTravelDeskRecord(record)"
                                        class="h-8 min-w-[92px] rounded-lg bg-[#1d54e2] px-4 text-[10px] font-semibold text-white transition hover:bg-[#1541b0]"
                                    >
                                        Return
                                    </button>


                                    <button
                                        x-show="record.movement === 'Returned' && Number(record.workflowVersion || 1) >= 3 && officialBusinessCanManage"
                                        type="button"
                                        @click="transitionOfficialBusinessStatus($data, record, 'for-completion')"
                                        class="h-8 min-w-[120px] rounded-lg bg-amber-600 px-4 text-[10px] font-semibold text-white transition hover:bg-amber-700"
                                    >
                                        Start Completion
                                    </button>

                                    <span
                                        x-show="record.movement === 'Returned' && !(Number(record.workflowVersion || 1) >= 3 && officialBusinessCanManage)"
                                        class="text-[10px] font-medium text-slate-400"
                                    >
                                        Returned
                                    </span>

                                </td>

                            </tr>
                        </template>


                        <!-- Empty State -->
                        <tr x-show="filteredTravelDeskRecords().length === 0">

                            <td
                                colspan="11"
                                class="px-4 py-14 text-center text-xs text-slate-400"
                            >
                                No travel records found.
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>


            <!-- Footer -->
            <div class="border-t border-slate-100 px-4 py-3">

                <p class="text-[9px] text-slate-400">
                    Travel Desk does not open the O.B. workspace. Its only operational actions are Depart and Returned.
                </p>

            </div>

        </div>

    </div>

    <!-- DELETED -->
    <div
        x-show="officialBusinessTab === 'deleted'"
        x-cloak
        class="px-6 py-5"
    >

        <!-- Header -->
        <div class="mb-4">
            <h1 class="text-xl font-bold text-slate-900">
                Deleted Official Business
            </h1>

            <p class="mt-1 text-[11px] text-slate-500">
                Recover deleted O.B. records or permanently delete them with administrator authority.
            </p>
        </div>


        <!-- Deleted Records Workspace -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">

            <!-- Toolbar -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-3 py-2">

                <div class="text-[11px] font-semibold text-slate-800">
                    Deleted Records
                </div>


                <!-- Search -->
                <div class="relative">

                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[9px] text-slate-400"></i>

                    <input
                        type="text"
                        x-model="deletedObSearch"
                        placeholder="Search deleted O.B."
                        class="h-9 w-[220px] rounded-md border border-slate-200 bg-white pl-8 pr-3 text-[10px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    >

                </div>

            </div>


            <!-- Table -->
            <div class="overflow-x-auto">

                <table class="w-full min-w-[1180px] table-fixed">

                    <thead class="bg-slate-50/70">

                        <tr class="border-b border-slate-200">

                            <th class="w-[13%] px-3 py-3 text-left text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                                OBF REF
                            </th>

                            <th class="w-[17%] px-3 py-3 text-left text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                                EMPLOYEE
                            </th>

                            <th class="w-[19%] px-3 py-3 text-left text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                                CLIENT / INTERNAL
                            </th>

                            <th class="w-[12%] px-3 py-3 text-left text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                                DELETED BY
                            </th>

                            <th class="w-[15%] px-3 py-3 text-left text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                                DELETED ON
                            </th>

                            <th class="w-[11%] px-3 py-3 text-left text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                                PREVIOUS STATUS
                            </th>

                            <th class="w-[13%] px-3 py-3 text-left text-[9px] font-semibold uppercase tracking-wide text-slate-500">
                                ACTIONS
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <template
                            x-for="record in filteredDeletedOfficialBusinessRecords()"
                            :key="'deleted-ob-' + record.ref"
                        >

                            <tr class="border-b border-slate-100 last:border-b-0 transition hover:bg-slate-50/70">

                                <!-- Ref -->
                                <td class="px-3 py-4">

                                    <span
                                        class="whitespace-nowrap text-[10px] font-medium text-slate-800"
                                        x-text="record.ref"
                                    ></span>

                                </td>


                                <!-- Employee -->
                                <td class="px-3 py-4">

                                    <span
                                        class="text-[10px] text-slate-700"
                                        x-text="record.employee"
                                    ></span>

                                </td>


                                <!-- Client -->
                                <td class="px-3 py-4">

                                    <span
                                        class="text-[10px] text-slate-700"
                                        x-text="record.client"
                                    ></span>

                                </td>


                                <!-- Deleted By -->
                                <td class="px-3 py-4">

                                    <span
                                        class="text-[10px] text-slate-700"
                                        x-text="record.deletedBy"
                                    ></span>

                                </td>


                                <!-- Deleted On -->
                                <td class="px-3 py-4">

                                    <span
                                        class="whitespace-nowrap text-[10px] text-slate-700"
                                        x-text="record.deletedOn"
                                    ></span>

                                </td>


                                <!-- Previous Status -->
                                <td class="px-3 py-4">

                                    <span
                                        class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-semibold text-slate-600"
                                        x-text="record.previousStatus"
                                    ></span>

                                </td>


                                <!-- Actions -->
                                <td class="px-3 py-4">

                                    <div class="flex items-center gap-1.5">

                                        <button
                                            type="button"
                                            @click="restoreDeletedOfficialBusiness(record)"
                                            class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-[9px] font-medium text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                                        >
                                            Restore
                                        </button>


                                        <button
                                            type="button"
                                            @click="permanentlyDeleteOfficialBusiness(record)"
                                            class="h-8 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 text-[9px] font-medium text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                                        >
                                            Delete Permanently
                                        </button>

                                    </div>

                                </td>

                            </tr>

                        </template>


                        <!-- Empty State -->
                        <tr x-show="filteredDeletedOfficialBusinessRecords().length === 0">

                            <td
                                colspan="7"
                                class="px-4 py-14 text-center text-xs text-slate-400"
                            >
                                No deleted official business records found.
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>


            <!-- Footer -->
            <div class="border-t border-slate-100 px-3 py-3">

                <span
                    class="text-[9px] text-slate-400"
                    x-text="filteredDeletedOfficialBusinessRecords().length + ' deleted record(s)'"
                ></span>

            </div>

        </div>

    </div>

</div>

<!-- =========================================================
     VISITOR LOG TAB CONTENT
========================================================= -->
<div
    x-show="activeTab === 'visitor-log'"
    x-cloak
    class="bg-[#f8fafc] min-h-[calc(100vh-8rem)]"
>
    <!-- =====================================================
         FRONT DESK
    ====================================================== -->
    <div
        x-show="visitorLogTab === 'front-desk'"
        x-cloak
        class="px-6 py-6"
    >

        <!-- Header -->
        <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-2">
                    <span class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                        Visitor Log
                    </span>

                    <span class="text-[10px] text-slate-300">
                        /
                    </span>

                    <span class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#1d54e2]">
                        Front Desk
                    </span>
                </div>

                <h1 class="text-[24px] font-bold leading-tight text-gray-900">
                    Front Desk
                </h1>

                <p class="mt-1 max-w-4xl text-xs leading-5 text-gray-500">
                    Reception handles visitor check-in and check-out. Browser GPS, Branch, and Activity links are optional for each visit.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    Visitor Access
                </button>

                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    Reports
                </button>
            </div>
        </div>

        <!-- =====================================================
             FRONT DESK
        ====================================================== -->

        <!-- Visitor Check-In -->
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">
                        Visitor Check-In
                    </h2>
                    <p class="mt-1 text-[11px] text-gray-500">
                        Search a returning visitor, or check in a first-time visitor.
                    </p>
                </div>

                <button
                    type="button"
                    @click="firstTimeVisitorOpen = true; visitorLogError = ''"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    <span class="mr-1 text-base leading-none">+</span>
                    First-Time Visitor
                </button>
            </div>

            <div class="flex flex-col gap-2 lg:flex-row">
                <div class="relative flex-1">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                        <i class="fas fa-search text-xs text-gray-400"></i>
                    </div>

                    <input
                        type="text"
                        x-model="visitorSearch"
                        @input.debounce.300ms="fetchVisitors()"
                        @keydown.enter.prevent="fetchVisitors()"
                        placeholder="Search name, mobile number, email, Visitor ID or Client ID"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white pl-10 pr-4 text-xs text-gray-700 outline-none transition placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >

                    <div
                        x-show="visitorSearchResults.length || visitorSearchLoading"
                        x-cloak
                        class="absolute left-0 right-0 top-full z-40 mt-1 max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                    >
                        <div x-show="visitorSearchLoading" class="px-4 py-3 text-xs text-gray-500">Searching...</div>
                        <template x-for="visitor in visitorSearchResults" :key="visitor.id">
                            <button
                                type="button"
                                @click="selectVisitor(visitor)"
                                class="block w-full border-t border-gray-100 px-4 py-3 text-left hover:bg-gray-50"
                            >
                                <span class="block text-xs font-medium text-gray-900" x-text="visitor.name"></span>
                                <span class="mt-1 block text-[10px] text-gray-400" x-text="[visitor.visitor_reference, visitor.mobile, visitor.company].filter(Boolean).join(' - ')"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <button
                    type="button"
                    @click="checkInVisitor()"
                    :disabled="visitorLogLoading"
                    class="h-11 rounded-lg bg-[#3159e8] px-7 text-xs font-semibold text-white shadow-sm transition hover:bg-[#2548c7]"
                >
                    <span x-text="visitorLogLoading ? 'Please wait...' : 'Check In'"></span>
                </button>
            </div>

            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                <input
                    type="number"
                    min="1"
                    x-model="visitorBranchId"
                    placeholder="Branch ID (optional)"
                    class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                <select
                    x-model="visitorActivityType"
                    @change="visitorActivityId = ''"
                    class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="">Activity type (optional)</option>
                    <option value="Task">Task</option>
                    <option value="Event">Event</option>
                    <option value="Call">Call</option>
                    <option value="Meeting">Meeting</option>
                </select>
                <select
                    x-model="visitorActivityId"
                    :disabled="!visitorActivityType"
                    class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-gray-50"
                >
                    <option value="">Activity (optional)</option>
                    <template x-for="activity in visitorActivityOptions" :key="`${visitorActivityType}-${activity.id}`">
                        <option :value="activity.id" x-text="activity.label"></option>
                    </template>
                </select>
            </div>

            <p x-show="visitorLogError" x-cloak class="mt-3 text-[11px] font-medium text-red-600" x-text="visitorLogError"></p>

            <!-- Automatic Location Notice -->
            <div class="mt-4 flex items-start gap-2 rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2">
                <span class="mt-[3px] h-2 w-2 shrink-0 rounded-full bg-blue-500"></span>

                <p class="text-[10px] leading-5 text-gray-500">
                    <span class="font-semibold text-gray-600">
                        Automatic location capture:
                    </span>
                    Check In and Check Out record device GPS when browser permission is available. Branch remains optional.
                    Check-in and check-out may be at different locations.
                </p>
            </div>
        </div>

        <div
            x-show="firstTimeVisitorOpen"
            x-cloak
            @keydown.escape.window="firstTimeVisitorOpen = false"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/40 p-4"
        >
            <form @submit.prevent="createFirstTimeVisitor()" class="w-full max-w-lg rounded-xl border border-gray-200 bg-white p-5 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">First-Time Visitor</h2>
                    <button type="button" @click="firstTimeVisitorOpen = false" class="text-gray-400 hover:text-gray-700">&times;</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input required x-model="newVisitor.name" type="text" placeholder="Name" class="h-10 rounded-lg border border-gray-300 px-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <input x-model="newVisitor.mobile" type="text" placeholder="Mobile (optional)" class="h-10 rounded-lg border border-gray-300 px-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <input x-model="newVisitor.email" type="email" placeholder="Email (optional)" class="h-10 rounded-lg border border-gray-300 px-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <input x-model="newVisitor.company_name" type="text" placeholder="Company (optional)" class="h-10 rounded-lg border border-gray-300 px-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                </div>
                <p x-show="visitorLogError" x-cloak class="mt-3 text-[11px] font-medium text-red-600" x-text="visitorLogError"></p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="firstTimeVisitorOpen = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700">Cancel</button>
                    <button type="submit" :disabled="firstTimeVisitorSaving" class="rounded-lg bg-[#3159e8] px-4 py-2 text-xs font-semibold text-white disabled:opacity-50" x-text="firstTimeVisitorSaving ? 'Creating...' : 'Create Visitor'"></button>
                </div>
            </form>
        </div>

        <!-- Currently Inside -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

            <!-- Section Header -->
            <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xs font-semibold text-gray-900">
                    Currently Inside
                </h2>

                <div class="flex items-center gap-2">
                    <div class="relative">
                        <input
                            type="text"
                            x-model="currentVisitorSearch"
                            placeholder="Search"
                            class="h-9 w-48 rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-500 transition hover:bg-gray-50"
                    >
                        <i class="fas fa-chevron-down text-[9px]"></i>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1150px] text-left">

                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/70 text-[9px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Visit Ref No.</th>
                            <th class="px-4 py-3">Visitor</th>
                            <th class="px-4 py-3">Company</th>
                            <th class="px-4 py-3">Time In / Location</th>
                            <th class="px-4 py-3">Activity</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">

                        <template x-for="visit in filteredCurrentVisits" :key="visit.id">
                            <tr class="align-top transition hover:bg-gray-50/60">
                                <td class="px-4 py-5">
                                    <button type="button" @click="viewVisitDetails(visit)" class="text-xs font-semibold text-blue-600 hover:text-blue-800" x-text="visit.visit_reference"></button>
                                </td>
                                <td class="px-4 py-5">
                                    <div class="text-xs font-medium text-gray-900" x-text="visit.visitor?.name || '-'"></div>
                                    <div class="mt-1 text-[10px] text-gray-400" x-text="visit.visitor?.visitor_reference || '-'"></div>
                                </td>
                                <td class="px-4 py-5 text-xs text-gray-700" x-text="visit.company || '-'"></td>
                                <td class="px-4 py-5">
                                    <div class="text-xs text-gray-700" x-text="formatVisitorDate(visit.checked_in_at)"></div>
                                    <div class="mt-1 text-[10px] text-gray-500" x-text="visitorMovementSummary(visit.check_in_movement)"></div>
                                </td>
                                <td class="px-4 py-5">
                                    <div class="text-xs text-gray-700" x-text="visit.activity?.label || 'Pending Link'"></div>
                                </td>
                                <td class="px-4 py-5">
                                    <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-[10px] font-semibold text-green-700" x-text="visit.status"></span>
                                </td>
                                <td class="px-4 py-5">
                                    <button
                                        type="button"
                                        @click="checkOutVisitor(visit)"
                                        :disabled="visitorLogLoading"
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                                    >
                                        Check Out
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="!filteredCurrentVisits.length">
                            <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-400">No visitors are currently checked in.</td>
                        </tr>

                        <!-- Visitor 1 -->
                        <tr x-show="false" class="align-top transition hover:bg-gray-50/60">

                            <td class="px-4 py-5">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-blue-600 hover:text-blue-800"
                                >
                                    VIS-20260814-0104
                                </button>
                            </td>

                            <td class="px-4 py-5">
                                <div class="text-xs font-medium text-gray-900">
                                    Wilfredo Dublin Jr.
                                </div>
                                <div class="mt-1 text-[10px] text-gray-400">
                                    VST-0001
                                </div>
                            </td>

                            <td class="px-4 py-5">
                                <div class="text-xs text-gray-700">
                                    Kapsel Medical Inc.
                                </div>
                            </td>

                            <td class="px-4 py-5">
                                <div class="flex flex-wrap items-center gap-1 text-xs text-gray-700">
                                    <span>Aug 18, 2026, 9:49 AM</span>

                                    <span class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[9px] font-medium text-gray-600">
                                        <i class="fas fa-map-marker-alt mr-1 text-[8px]"></i>
                                        Cebu Office
                                    </span>
                                </div>

                                <div class="mt-2 text-[10px] font-semibold text-gray-600">
                                    John Kelly &amp; Company - Cebu Office
                                </div>

                                <div class="mt-1 text-[9px] text-gray-400">
                                    Cebu Holdings Center, Cebu Business Park, Cebu City - +/-21m - GPS
                                </div>
                            </td>

                            <td class="px-4 py-5">
                                <div class="text-xs text-gray-700">
                                    Kapsel Board &amp; Governance Meeting
                                </div>
                                <div class="mt-1 text-[10px] text-gray-400">
                                    Kapsel Medical Inc.
                                </div>
                            </td>

                            <td class="px-4 py-5">
                                <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-[10px] font-semibold text-green-700">
                                    Checked In
                                </span>
                            </td>

                            <td class="px-4 py-5">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-medium text-gray-700 transition hover:bg-gray-50"
                                    >
                                        Check Out
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-medium text-gray-700 transition hover:bg-gray-50"
                                    >
                                        Manual
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Visitor 2 -->
                        <tr x-show="false" class="align-top transition hover:bg-gray-50/60">

                            <td class="px-4 py-5">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-blue-600 hover:text-blue-800"
                                >
                                    VIS-20260814-0103
                                </button>
                            </td>

                            <td class="px-4 py-5">
                                <div class="text-xs font-medium text-gray-900">
                                    Robert Lim
                                </div>
                                <div class="mt-1 text-[10px] text-gray-400">
                                    VST-0004
                                </div>
                            </td>

                            <td class="px-4 py-5">
                                <div class="text-xs text-gray-700">
                                    Sanarex Med Group Inc.
                                </div>
                            </td>

                            <td class="px-4 py-5">
                                <div class="flex flex-wrap items-center gap-1 text-xs text-gray-700">
                                    <span>Aug 18, 2026, 10:14 AM</span>

                                    <span class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[9px] font-medium text-gray-600">
                                        <i class="fas fa-map-marker-alt mr-1 text-[8px]"></i>
                                        Branch 02
                                    </span>
                                </div>

                                <div class="mt-2 text-[10px] font-semibold text-gray-600">
                                    Demo Brand - Branch 02
                                </div>

                                <div class="mt-1 text-[9px] text-gray-400">
                                    Demo configured location - +/-24m - GPS
                                </div>
                            </td>

                            <td class="px-4 py-5">
                                <div class="text-xs text-gray-400">
                                    Not linked
                                </div>
                            </td>

                            <td class="px-4 py-5">
                                <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-[10px] font-semibold text-green-700">
                                    Checked In
                                </span>
                            </td>

                            <td class="px-4 py-5">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-medium text-gray-700 transition hover:bg-gray-50"
                                    >
                                        Check Out
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-medium text-gray-700 transition hover:bg-gray-50"
                                    >
                                        Manual
                                    </button>
                                </div>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>

            <!-- Bottom Note -->
            <div class="border-t border-gray-200 px-4 py-3">
                <p class="text-[10px] text-gray-400">
                    <span x-text="currentVisits.length"></span> visitor(s) currently inside &middot; Reception only manages check-in and check-out &middot; GPS/branch captured per event.
                </p>
            </div>

        </div>
    </div>

    <!-- =====================================================
         VISIT RECORDS
    ====================================================== -->
    <div
        x-show="visitorLogTab === 'visit-records'"
        x-cloak
        class="px-6 py-6"
    >
        <!-- Header -->
        <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-[24px] font-bold leading-tight text-gray-900">
                    Visitor Log
                </h1>

                <p class="mt-1 max-w-4xl text-xs leading-5 text-gray-500">
                    Reception handles check-in and check-out; browser GPS, Branch, and Activity links are optional.
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    Visitor Access
                </button>

                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    Reports
                </button>
            </div>
        </div>

        <!-- Visit Records Card -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

            <!-- Card Header -->
            <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xs font-semibold text-gray-900">
                    Visit Records
                </h2>

                <div class="flex items-center gap-2">

                    <!-- Search -->
                    <div class="relative">
                        <input
                            type="text"
                            x-model="visitRecordSearch"
                            @input.debounce.350ms="fetchVisitRecords()"
                            placeholder="Search"
                            class="h-9 w-48 rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <!-- Filter -->
                    <div
                        class="relative"
                        @click.away="visitRecordFilterOpen = false"
                    >
                        <button
                            type="button"
                            @click="visitRecordFilterOpen = !visitRecordFilterOpen"
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-50"
                        >
                            <i
                                class="fas fa-chevron-down text-[9px] transition-transform"
                                :class="visitRecordFilterOpen ? 'rotate-180' : ''"
                            ></i>
                        </button>

                        <div
                            x-show="visitRecordFilterOpen"
                            x-cloak
                            class="absolute right-0 top-full z-50 mt-2 w-40 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                        >
                            <button
                                type="button"
                                @click="setVisitRecordStatus('')"
                                class="block w-full px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50"
                            >
                                All Records
                            </button>

                            <button
                                type="button"
                                @click="setVisitRecordStatus('Checked In')"
                                class="block w-full px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50"
                            >
                                Checked In
                            </button>

                            <button
                                type="button"
                                @click="setVisitRecordStatus('Completed')"
                                class="block w-full px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50"
                            >
                                Completed
                            </button>

                            <button
                                type="button"
                                @click="setVisitRecordStatus('Pending Link')"
                                class="block w-full px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50"
                            >
                                Pending Link
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <p x-show="visitorLogError" x-cloak class="border-b border-gray-200 px-4 py-3 text-[11px] font-medium text-red-600" x-text="visitorLogError"></p>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-[1450px] w-full border-collapse">

                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/70 text-left">
                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Visit Ref No.
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Visitor
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Company
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Time In / Location
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Time Out / Location
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Related Activity
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Status
                            </th>

                            <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">

                        <template x-for="visit in visitRecords" :key="visit.id">
                            <tr class="transition hover:bg-gray-50/60">
                                <td class="whitespace-nowrap px-4 py-5 align-top">
                                    <button type="button" @click="viewVisitDetails(visit)" class="text-xs font-semibold text-[#1d54e2] hover:underline" x-text="visit.visit_reference"></button>
                                </td>
                                <td class="px-4 py-5 align-top">
                                    <div class="text-xs font-medium text-gray-900" x-text="visit.visitor?.name || '-'"></div>
                                    <div class="mt-1 text-[10px] text-slate-400" x-text="visit.visitor?.visitor_reference || '-'"></div>
                                </td>
                                <td class="px-4 py-5 align-top text-xs text-gray-800" x-text="visit.company || '-'"></td>
                                <td class="px-4 py-5 align-top">
                                    <div class="text-xs text-gray-800" x-text="formatVisitorDate(visit.checked_in_at)"></div>
                                    <div class="mt-1 text-[10px] text-slate-500" x-text="visitorMovementSummary(visit.check_in_movement)"></div>
                                </td>
                                <td class="px-4 py-5 align-top">
                                    <div class="text-xs text-gray-800" x-text="formatVisitorDate(visit.checked_out_at)"></div>
                                    <div class="mt-1 text-[10px] text-slate-500" x-text="visitorMovementSummary(visit.check_out_movement)"></div>
                                </td>
                                <td class="px-4 py-5 align-top">
                                    <div class="text-xs text-gray-800" x-text="visit.activity?.label || 'Pending Link'"></div>
                                    <div x-show="visit.activity" class="mt-1 text-[10px] text-slate-400" x-text="visit.activity?.type"></div>
                                </td>
                                <td class="px-4 py-5 align-top">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-[10px] font-semibold"
                                        :class="visit.status === 'Completed' ? 'bg-indigo-50 text-indigo-700' : 'bg-green-50 text-green-700'"
                                        x-text="visit.status"
                                    ></span>
                                    <div x-show="!visit.activity" class="mt-1 text-[9px] text-amber-600">Pending Link</div>
                                </td>
                                <td class="px-4 py-5 align-top">
                                    <button type="button" @click="viewVisitDetails(visit)" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-medium text-gray-700 transition hover:bg-gray-50">View</button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="!visitRecords.length">
                            <td colspan="8" class="px-4 py-8 text-center text-xs text-gray-400">No visit records found.</td>
                        </tr>

                        <!-- Wilfredo Dublin Jr. -->
                        <tr
                            class="transition hover:bg-gray-50/60"
                            x-show="false"
                        >
                            <td class="whitespace-nowrap px-4 py-5 align-top">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-[#1d54e2] hover:underline"
                                >
                                    VIS-20260814-0104
                                </button>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs font-medium text-gray-900">
                                    Wilfredo Dublin Jr.
                                </div>
                                <div class="mt-1 text-[10px] text-slate-400">
                                    VST-0001
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs text-gray-800">
                                    Kapsel Medical Inc.
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="flex flex-wrap items-center gap-1 text-xs text-gray-800">
                                    <span>Aug 18, 2026, 9:49 AM</span>

                                    <span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[9px] font-medium text-slate-600">
                                        <i class="fas fa-map-marker-alt mr-1 text-[7px] text-blue-500"></i>
                                        Cebu Office
                                    </span>
                                </div>

                                <div class="mt-1 text-[10px] font-semibold text-slate-700">
                                    John Kelly &amp; Company - Cebu Office
                                </div>

                                <div class="mt-0.5 text-[9px] text-slate-500">
                                    Cebu Holdings Center, Cebu Business Park, Cebu City - +/-21m - GPS
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs font-medium text-amber-600">
                                    Not checked out
                                </div>

                                <div class="mt-1 text-[9px] text-slate-400">
                                    Check-out location not yet recorded
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs text-gray-800">
                                    Kapsel Board &amp; Governance Meeting
                                </div>

                                <div class="mt-1 text-[10px] text-slate-400">
                                    Kapsel Medical Inc.
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-[10px] font-semibold text-green-700">
                                    Checked In
                                </span>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <button
                                    type="button"
                                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-medium text-gray-700 transition hover:bg-gray-50"
                                >
                                    View
                                </button>
                            </td>
                        </tr>

                        <!-- Robert Lim -->
                        <tr
                            class="transition hover:bg-gray-50/60"
                            x-show="false"
                        >
                            <td class="whitespace-nowrap px-4 py-5 align-top">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-[#1d54e2] hover:underline"
                                >
                                    VIS-20260814-0103
                                </button>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs font-medium text-gray-900">
                                    Robert Lim
                                </div>
                                <div class="mt-1 text-[10px] text-slate-400">
                                    VST-0004
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs text-gray-800">
                                    Sanarex Med Group Inc.
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="flex flex-wrap items-center gap-1 text-xs text-gray-800">
                                    <span>Aug 18, 2026, 10:14 AM</span>

                                    <span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[9px] font-medium text-slate-600">
                                        <i class="fas fa-map-marker-alt mr-1 text-[7px] text-blue-500"></i>
                                        Branch 02
                                    </span>
                                </div>

                                <div class="mt-1 text-[10px] font-semibold text-slate-700">
                                    Demo Brand - Branch 02
                                </div>

                                <div class="mt-0.5 text-[9px] text-slate-500">
                                    Demo configured location - +/-24m - GPS
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs font-medium text-amber-600">
                                    Not checked out
                                </div>

                                <div class="mt-1 text-[9px] text-slate-400">
                                    Check-out location not yet recorded
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs text-amber-600">
                                    Not linked
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-[10px] font-semibold text-green-700">
                                    Checked In
                                </span>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <button
                                    type="button"
                                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-medium text-gray-700 transition hover:bg-gray-50"
                                >
                                    View
                                </button>
                            </td>
                        </tr>

                        <!-- Sheila Tan -->
                        <tr
                            class="transition hover:bg-gray-50/60"
                            x-show="false"
                        >
                            <td class="whitespace-nowrap px-4 py-5 align-top">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-[#1d54e2] hover:underline"
                                >
                                    VIS-20260814-0102
                                </button>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs font-medium text-gray-900">
                                    Sheila Tan
                                </div>
                                <div class="mt-1 text-[10px] text-slate-400">
                                    VST-0002
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs text-gray-800">
                                    Seollem Management
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="flex flex-wrap items-center gap-1 text-xs text-gray-800">
                                    <span>Aug 18, 2026, 7:57 AM</span>

                                    <span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[9px] font-medium text-slate-600">
                                        <i class="fas fa-map-marker-alt mr-1 text-[7px] text-blue-500"></i>
                                        Cebu Office
                                    </span>
                                </div>

                                <div class="mt-1 text-[10px] font-semibold text-slate-700">
                                    John Kelly &amp; Company - Cebu Office
                                </div>

                                <div class="mt-0.5 text-[9px] text-slate-500">
                                    Cebu Holdings Center, Cebu Business Park, Cebu City - +/-21m - GPS
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="flex flex-wrap items-center gap-1 text-xs text-gray-800">
                                    <span>Aug 18, 2026, 8:41 AM</span>

                                    <span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[9px] font-medium text-slate-600">
                                        <i class="fas fa-map-marker-alt mr-1 text-[7px] text-blue-500"></i>
                                        Cebu Office
                                    </span>
                                </div>

                                <div class="mt-1 text-[10px] font-semibold text-slate-700">
                                    John Kelly &amp; Company - Cebu Office
                                </div>

                                <div class="mt-0.5 text-[9px] text-slate-500">
                                    Cebu Holdings Center, Cebu Business Park, Cebu City - +/-21m - GPS
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs text-gray-800">
                                    Seollem Document Turnover
                                </div>

                                <div class="mt-1 text-[10px] text-slate-400">
                                    Seollem Management
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 text-[10px] font-semibold text-indigo-700">
                                    Completed
                                </span>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <button
                                    type="button"
                                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-medium text-gray-700 transition hover:bg-gray-50"
                                >
                                    View
                                </button>
                            </td>
                        </tr>

                        <!-- Anna Cruz -->
                        <tr
                            class="transition hover:bg-gray-50/60"
                            x-show="false"
                        >
                            <td class="whitespace-nowrap px-4 py-5 align-top">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-[#1d54e2] hover:underline"
                                >
                                    VIS-20260814-0101
                                </button>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs font-medium text-gray-900">
                                    Anna Cruz
                                </div>
                                <div class="mt-1 text-[10px] text-slate-400">
                                    VST-0003
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs text-gray-800">
                                    &mdash;
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="flex flex-wrap items-center gap-1 text-xs text-gray-800">
                                    <span>Aug 18, 2026, 6:59 AM</span>

                                    <span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[9px] font-medium text-slate-600">
                                        <i class="fas fa-map-marker-alt mr-1 text-[7px] text-blue-500"></i>
                                        Cebu Office
                                    </span>
                                </div>

                                <div class="mt-1 text-[10px] font-semibold text-slate-700">
                                    John Kelly &amp; Company - Cebu Office
                                </div>

                                <div class="mt-0.5 text-[9px] text-slate-500">
                                    Cebu Holdings Center, Cebu Business Park, Cebu City - +/-21m - GPS
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="flex flex-wrap items-center gap-1 text-xs text-gray-800">
                                    <span>Aug 18, 2026, 7:37 AM</span>

                                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[9px] font-medium text-amber-700">
                                        Off-site
                                    </span>
                                </div>

                                <div class="mt-1 text-[10px] font-semibold text-slate-700">
                                    Off-site / Unrecognized Location
                                </div>

                                <div class="mt-0.5 text-[9px] text-slate-500">
                                    Outside configured geofence - +/-32m - GPS
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <div class="text-xs text-amber-600">
                                    Not linked
                                </div>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-[10px] font-semibold text-amber-700">
                                    Pending Link
                                </span>
                            </td>

                            <td class="px-4 py-5 align-top">
                                <button
                                    type="button"
                                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-medium text-gray-700 transition hover:bg-gray-50"
                                >
                                    View
                                </button>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="border-t border-gray-200 px-4 py-3">
                <p class="text-[10px] text-gray-400">
                    <span x-text="visitRecords.length"></span> visit record(s) &middot; Check-in creates the record immediately &middot; Each event keeps its own GPS / brand / branch.
                </p>
            </div>

        </div>
    </div>

</div>
<div x-show="activeTab === 'meetings'" x-cloak class="bg-white">

    <div class="p-4">

        <!-- Header -->
        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">
                    Meetings
                </h1>

                <p class="mt-1 text-xs text-gray-500">
                    Schedule, manage, document, and report meetings.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
<button
                    type="button"
                    @click="showExportModal = true"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-gray-700 transition hover:border-blue-300 hover:text-[#1d54e2] focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    <i class="fas fa-ellipsis-v mr-2 text-[10px] text-gray-400"></i>
                    Reports
                </button>

                <button
                    type="button"
                    @click="showMeetingModal = true"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#1d54e2] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1541b0] focus:outline-none focus:ring-2 focus:ring-blue-100"
                >
                    <i class="fas fa-plus mr-2 text-[10px]"></i>
                    New Meeting
                </button>
            </div>

        </div>


        <!-- Meetings workspace -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <!-- Toolbar -->
            <div class="flex flex-col gap-4 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between py-3">

                <!-- Tabs -->
                <div class="flex items-center gap-1">

                    <button
                        type="button"
                        @click="meetingsSubTab = 'all'"
                        :class="meetingsSubTab === 'all'
                            ? 'bg-blue-50 text-blue-700'
                            : 'bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700'"
                        class="rounded-md px-3 py-2 text-[11px] font-semibold transition"
                    >
                        All Meetings
                    </button>

                    <button
                        type="button"
                        @click="meetingsSubTab = 'upcoming'"
                        :class="meetingsSubTab === 'upcoming'
                            ? 'bg-blue-50 text-blue-700'
                            : 'bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700'"
                        class="rounded-md px-3 py-2 text-[11px] font-semibold transition"
                    >
                        Upcoming
                    </button>

                    <button
                        type="button"
                        @click="meetingsSubTab = 'completed'"
                        :class="meetingsSubTab === 'completed'
                            ? 'bg-blue-50 text-blue-700'
                            : 'bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700'"
                        class="rounded-md px-3 py-2 text-[11px] font-semibold transition"
                    >
                        Completed
                    </button>

                </div>


                <!-- Search and filters -->
                <div class="grid w-full grid-cols-1 gap-3 md:grid-cols-3 lg:max-w-[820px]">

                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-400"></i>

                        <input
                            type="text"
                            x-model="meetingSearch"
                            placeholder="Search meetings"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white pl-8 pr-3 text-xs text-gray-700 outline-none placeholder:text-gray-400 transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <select
                        x-model="meetingModeFilter"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="all">All Modes</option>
                        <option value="physical">Physical</option>
                        <option value="online">Online</option>
                        <option value="hybrid">Hybrid</option>
                    </select>

                    <select
                        x-model="meetingStatusFilter"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="all">All Statuses</option>
                        <option value="upcoming">Scheduled</option>
                        <option value="completed">Completed</option>
                    </select>

                </div>

            </div>


            <!-- Table -->
            <div class="overflow-x-auto">

                <table class="w-full min-w-[1180px] table-fixed text-left">

                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/80">

                                                        <th class="w-12 px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    class="meeting-select-all h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                    aria-label="Select all meetings"
                                >
                            </th>
<th class="w-[18%] px-4 py-3 text-[9px] font-semibold uppercase tracking-[0.04em] text-gray-500">
                                Meeting
                            </th>

                            <th class="w-[13%] px-3 py-3 text-[9px] font-semibold uppercase tracking-[0.04em] text-gray-500">
                                Related Account
                            </th>

                            <th class="w-[11%] px-3 py-3 text-[9px] font-semibold uppercase tracking-[0.04em] text-gray-500">
                                Date / Time
                            </th>

                            <th class="w-[8%] px-3 py-3 text-[9px] font-semibold uppercase tracking-[0.04em] text-gray-500">
                                Mode
                            </th>

                            <th class="w-[8%] px-3 py-3 text-[9px] font-semibold uppercase tracking-[0.04em] text-gray-500">
                                Participants
                            </th>

                            <th class="w-[9%] px-3 py-3 text-[9px] font-semibold uppercase tracking-[0.04em] text-gray-500">
                                Status
                            </th>

                            <th class="w-[8%] px-3 py-3 text-[9px] font-semibold uppercase tracking-[0.04em] text-gray-500">
                                Estimated
                            </th>

                            <th class="w-[7%] px-3 py-3 text-[9px] font-semibold uppercase tracking-[0.04em] text-gray-500">
                                Actual
                            </th>

                            <th class="w-[12%] px-3 py-3 text-[9px] font-semibold uppercase tracking-[0.04em] text-gray-500">
                                Documentation
                            </th>

                            <th class="w-[6%] px-4 py-3 text-right text-[9px] font-semibold uppercase tracking-[0.04em] text-gray-500">
                                Actions
                            </th>

                        </tr>
                    </thead>


                    <tbody class="divide-y divide-gray-100 bg-white">

                        <template
                            x-for="meeting in filteredMeetings.slice((currentPage - 1) * perPage, currentPage * perPage)"
                            :key="meeting.id"
                        >

                            <tr class="group transition-colors hover:bg-slate-50/70">

                                <!-- Meeting -->
                                                                <td class="w-12 px-4 py-4">
                                    <input
                                        type="checkbox"
                                        class="meeting-row-checkbox h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                                        aria-label="Select meeting"
                                    >
                                </td>
<td class="px-4 py-4 align-middle">
                                    <button
                                        type="button"
                                        @click="openCoreActivityDetails('meeting', meeting)"
                                        class="block min-w-0 max-w-full text-left"
                                    >
                                        <div
                                            class="truncate text-xs font-semibold leading-5 text-[#1d54e2] transition group-hover:text-blue-700"
                                            x-text="meeting.title || 'Meeting'"
                                        ></div>

                                        <div
                                            class="truncate text-[9px] leading-4 text-gray-400"
                                            x-text="meeting.meeting_type || meeting.type || 'Client Meeting'"
                                        ></div>
                                    </button>
                                </td>


                                <!-- Related Account -->
                                <td class="px-3 py-4 align-middle">
                                    <div
                                        class="truncate text-[10px] font-medium text-gray-700"
                                        x-text="meeting.related_account || meeting.account || meeting.client || '-'"
                                    ></div>
                                </td>


                                <!-- Date / Time -->
                                <td class="px-3 py-4 align-middle">
                                    <div
                                        class="whitespace-nowrap text-[10px] font-medium text-gray-700"
                                        x-text="meeting.date || '-'"
                                    ></div>

                                    <div
                                        class="mt-0.5 whitespace-nowrap text-[9px] text-gray-400"
                                        x-text="meeting.time || ''"
                                    ></div>
                                </td>


                                <!-- Mode -->
                                <td class="px-3 py-4 align-middle">
                                    <span
                                        class="inline-flex whitespace-nowrap rounded-full bg-gray-100 px-2.5 py-1 text-[9px] font-medium text-gray-600"
                                        x-text="meeting.mode || meeting.location || 'Physical'"
                                    ></span>
                                </td>


                                <!-- Participants -->
                                <td
                                    class="px-3 py-4 align-middle text-[10px] font-medium text-gray-700"
                                    x-text="meeting.attendees || meeting.participants || 0"
                                ></td>


                                <!-- Status -->
                                <td class="px-3 py-4 align-middle">
                                    <span
                                        class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[9px] font-semibold"
                                        :class="meeting.status === 'completed'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-blue-50 text-blue-600'"
                                        x-text="meeting.status === 'completed' ? 'Completed' : 'Scheduled'"
                                    ></span>
                                </td>


                                <!-- Estimated -->
                                <td
                                    class="px-3 py-4 align-middle text-[10px] text-gray-700"
                                    x-text="meeting.duration || '-'"
                                ></td>


                                <!-- Actual -->
                                <td
                                    class="px-3 py-4 align-middle text-[10px] text-gray-700"
                                    x-text="meeting.actual_duration || meeting.actual || '-'"
                                ></td>


                                <!-- Documentation -->
                                <td class="px-3 py-4 align-middle">

                                    <div class="flex flex-wrap items-center gap-1">

                                        <template x-if="meeting.has_minutes">
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-gray-100 px-2 py-1 text-[8px] font-medium text-gray-600">
                                                Minutes &#10003;
                                            </span>
                                        </template>

                                        <template x-if="meeting.has_video || meeting.has_audio">
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-gray-100 px-2 py-1 text-[8px] font-medium text-gray-600">
                                                Recording &#10003;
                                            </span>
                                        </template>

                                        <template x-if="meeting.has_transcript">
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-gray-100 px-2 py-1 text-[8px] font-medium text-gray-600">
                                                Transcript &#10003;
                                            </span>
                                        </template>

                                        <template x-if="meeting.file_count || (meeting.files && meeting.files.length)">
                                            <span
                                                class="inline-flex whitespace-nowrap rounded-full bg-gray-100 px-2 py-1 text-[8px] font-medium text-gray-600"
                                                x-text="(meeting.file_count || meeting.files.length) + ((meeting.file_count || meeting.files.length) === 1 ? ' File' : ' Files')"
                                            ></span>
                                        </template>

                                        <template x-if="!meeting.has_minutes && !meeting.has_video && !meeting.has_audio && !meeting.has_transcript && !meeting.file_count && !(meeting.files && meeting.files.length)">
                                            <span class="text-[10px] text-gray-400">&mdash;</span>
                                        </template>

                                    </div>

                                </td>


                                <!-- Actions -->
                                <td class="px-4 py-4 text-right align-middle">

                                    <div class="flex items-center justify-end gap-1">

                                        <button
                                            type="button"
                                            @click="openCoreActivityDetails('meeting', meeting)"
                                            class="h-8 rounded-md border border-gray-200 bg-white px-3 text-[10px] font-semibold text-gray-600 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600"
                                        >
                                            Open
                                        </button>

                                        <button
                                            type="button"
                                            @click="editMeeting(meeting)"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-blue-50 hover:text-[#1d54e2]"
                                            title="Edit meeting"
                                        >
                                            <i class="fas fa-pencil-alt text-[10px]"></i>
                                        </button>

                                    </div>

                                </td>

                            </tr>

                        </template>


                        <!-- Empty State -->
                        <template x-if="filteredMeetings.length === 0">
                            <tr>
                                <td colspan="11" class="px-6 py-16 text-center">

                                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <i class="far fa-calendar-alt"></i>
                                    </div>

                                    <div class="mt-3 text-sm font-semibold text-gray-800">
                                        No meetings found
                                    </div>

                                    <div class="mt-1 text-xs text-gray-400">
                                        Try adjusting the meeting filters or schedule a new meeting.
                                    </div>

                                </td>
                            </tr>
                        </template>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>
        <!-- Footer / Pagination Area -->
        <div x-show="activeTab === 'events' || activeTab === 'call'" class="px-6 py-3 bg-white border-t border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-3 text-xs font-semibold text-gray-500" x-show="activeTab === 'task'">
                <span class="text-gray-600">Total task <span class="text-gray-800" x-text="taskCounts.total"></span></span>
                <div class="w-px h-3 bg-gray-300"></div>
                <span class="text-gray-600">Open Task <span class="text-blue-600" x-text="taskCounts.open"></span></span>
                <div class="w-px h-3 bg-gray-300"></div>
                <span class="text-gray-600">Completed <span class="text-green-500" x-text="taskCounts.completed"></span></span>
                <div class="w-px h-3 bg-gray-300"></div>
                <span class="text-gray-600">Overdue <span class="text-red-500" x-text="taskCounts.overdue"></span></span>
            </div>

            <div class="flex items-center gap-3 text-xs font-semibold text-gray-500" x-show="activeTab === 'events'" style="display: none;">
                <span class="text-gray-600">Total Event <span class="text-gray-800" x-text="eventCounts.total"></span></span>
                <div class="w-px h-3 bg-gray-300"></div>
                <span class="text-gray-600">Upcoming Events <span class="text-blue-600" x-text="eventCounts.upcoming"></span></span>
                <div class="w-px h-3 bg-gray-300"></div>
                <span class="text-gray-600">Closed Events <span class="text-green-500" x-text="eventCounts.closed"></span></span>
            </div>

            <div class="flex items-center gap-3 text-xs font-semibold text-gray-500" x-show="activeTab === 'meetings'" style="display: none;">
                <span class="text-gray-600">Total Meetings <span class="text-gray-800" x-text="meetingCounts.total"></span></span>
                <div class="w-px h-3 bg-gray-300"></div>
                <span class="text-gray-600">Upcoming <span class="text-blue-600" x-text="meetingCounts.upcoming"></span></span>
                <div class="w-px h-3 bg-gray-300"></div>
                <span class="text-gray-600">Completed <span class="text-green-500" x-text="meetingCounts.completed"></span></span>
            </div>

            <div class="flex items-center gap-3 text-xs font-semibold text-gray-500" x-show="activeTab === 'call'" style="display: none;">
                <span class="text-gray-600">Total Calls <span class="text-gray-800" x-text="callCounts.total"></span></span>
                <div class="w-px h-3 bg-gray-300"></div>
                <span class="text-gray-600">Scheduled <span class="text-blue-600" x-text="callCounts.scheduled"></span></span>
                <div class="w-px h-3 bg-gray-300"></div>
                <span class="text-gray-600">Overdue <span class="text-red-500" x-text="callCounts.overdue"></span></span>
            </div>

            <div class="flex items-center gap-4 text-xs font-semibold text-gray-500 tracking-wide">
                <div class="flex items-center gap-2 relative" @click.away="perPageOpen = false">
                    <span class="text-gray-600">Records per page</span>
                    <button @click="perPageOpen = !perPageOpen" class="focus:outline-none flex items-center gap-1 text-gray-800 hover:text-black transition">
                        <span x-text="perPage"></span> <i class="fas fa-chevron-down text-[9px]" :class="perPageOpen ? 'rotate-180' : ''"></i>
                    </button>
                    <!-- Dropdown -->
                    <div x-show="perPageOpen"
                         style="display: none;"
                         class="absolute bottom-full left-0 mb-1 w-20 bg-white rounded shadow-lg border border-gray-200 py-1 z-50 text-gray-800 text-xs font-normal">
                        <template x-for="option in perPageOptions" :key="option">
                            <button @click="perPage = option; perPageOpen = false"
                                    class="w-full text-left px-3 py-1.5 hover:bg-gray-100 transition"
                                    :class="perPage === option ? 'bg-gray-100 font-bold text-black' : ''"
                                    x-text="option">
                            </button>
                        </template>
                    </div>
                </div>
                <div class="w-px h-3 bg-gray-300"></div>
                <span class="text-gray-600"><span class="text-gray-800" x-text="activeList.length > 0 ? ((currentPage - 1) * perPage) + 1 : '0'"></span> - <span class="text-gray-800" x-text="Math.min(currentPage * perPage, activeList.length)"></span> of <span class="text-gray-800" x-text="activeList.length"></span></span>
                <div class="flex items-center gap-3 text-gray-400">
                    <button @click="if(currentPage > 1) currentPage--" :disabled="currentPage === 1" class="hover:text-gray-800 transition disabled:opacity-50"><i class="fas fa-chevron-left text-[11px]"></i></button>
                    <button @click="if(currentPage * perPage < activeList.length) currentPage++" :disabled="currentPage * perPage >= activeList.length" class="hover:text-gray-800 transition disabled:opacity-50"><i class="fas fa-chevron-right text-[11px]"></i></button>
                </div>
            </div>
        </div>

    </div>

    </div><!-- end list/view content; keep mounted to preserve filters and pagination -->

    <!-- Modal Backdrop for editing, creation, tools, export, and media only -->
    <div x-show="showTaskModal || taskToolModal || showMeetingModal || showCallModal || showEventModal || showComposeEmail || showExportModal || showVideoPlayer"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="showTaskModal = false; taskToolModal = null; showMeetingModal = false; showCallModal = false; showEventModal = false; closeComposeEmail(); showExportModal = false; showVideoPlayer = false;"
         class="fixed inset-0 bg-gray-900/40 backdrop-blur-[2px] z-[90]"
         style="display: none;">
    </div>

    <!-- Task V2 tools: existing visual language, wired to the shared task records. -->
    <div x-show="taskToolModal" x-cloak class="fixed inset-0 z-[101] flex items-center justify-center p-3 sm:p-5 pointer-events-none">
        <section @click.stop class="pointer-events-auto flex max-h-[calc(100vh-2rem)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <header class="flex items-center justify-between border-b border-slate-200 px-4 py-4 sm:px-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900" x-text="taskToolModal === 'bulk' ? 'Bulk Edit Tasks' : taskToolModal === 'templates' ? 'Task Templates' : 'Outline Builder'"></h2>
                    <p class="mt-1 text-xs text-slate-500" x-text="taskToolModal === 'bulk' ? `${selectedTasks.length} selected task(s)` : taskToolModal === 'templates' ? 'Create a new task tree from a saved template.' : 'Paste tasks or adjust the hierarchy of existing records.'"></p>
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-500">Direction</label>
                    <select x-model="newMessage.direction" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700">
                        <option value="Conversation Thread">Conversation Thread</option>
                        <option value="Incoming">Incoming</option>
                        <option value="Outgoing">Outgoing</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-500">Duration (minutes)</label>
                    <input type="number" min="0" x-model.number="newMessage.durationMinutes" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-[11px] font-semibold text-slate-500">Professional conversation summary</label>
                    <textarea rows="2" x-model="newMessage.conversationSummary" placeholder="Summarize the business context and outcome." class="w-full resize-none rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700"></textarea>
                </div>
                <button type="button" @click="taskToolModal = null" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 hover:text-slate-700" aria-label="Close task tool"><i class="fas fa-times"></i></button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                <div x-show="taskToolModal === 'bulk'" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="text-xs font-semibold text-slate-700">Status
                            <select x-model="taskBulkStatus" class="mt-2 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm">
                                <option value="">Keep current</option>
                                <template x-for="status in taskStatusValues" :key="status"><option :value="status" x-text="status"></option></template>
                            </select>
                        </label>
                        <label class="text-xs font-semibold text-slate-700">Priority
                            <select x-model="taskBulkPriority" class="mt-2 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm">
                                <option value="">Keep current</option><option>Low</option><option>Normal</option><option>High</option><option>Urgent</option>
                            </select>
                        </label>
                    </div>
                    <div class="flex flex-wrap justify-between gap-3 border-t border-slate-100 pt-4">
                        <button type="button" @click="archiveSelectedTasks()" :disabled="taskToolBusy" class="h-10 rounded-lg border border-red-200 bg-red-50 px-4 text-xs font-semibold text-red-700 disabled:opacity-50">Archive selected branches</button>
                        <button type="button" @click="applyTaskBulkEdit()" :disabled="taskToolBusy || (!taskBulkStatus && !taskBulkPriority)" class="h-10 rounded-lg bg-blue-600 px-5 text-xs font-semibold text-white disabled:opacity-50">Apply changes</button>
                    </div>
                </div>

                <div x-show="taskToolModal === 'templates'" class="space-y-3">
                    <p x-show="taskTemplatesLoading" class="py-8 text-center text-sm text-slate-400">Loading templates...</p>
                    <template x-for="template in taskTemplates" :key="template.id">
                        <div class="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div><p class="text-sm font-semibold text-slate-900" x-text="template.title"></p><p class="mt-1 text-xs text-slate-500" x-text="template.description || 'Reusable task tree'"></p></div>
                            <button type="button" @click="instantiateTaskTemplate(template)" :disabled="taskToolBusy" class="h-9 shrink-0 rounded-lg bg-blue-600 px-4 text-xs font-semibold text-white disabled:opacity-50">Create Tasks</button>
                        </div>
                    </template>
                    <p x-show="!taskTemplatesLoading && !taskTemplates.length" class="rounded-xl border border-dashed border-slate-200 px-5 py-10 text-center text-sm text-slate-400">No saved task templates yet. Open a task and choose Save as Template.</p>
                </div>

                <div x-show="taskToolModal === 'outline'" class="space-y-5">
                    <label class="block text-xs font-semibold text-slate-700">Paste an indented outline
                        <textarea x-model="taskOutlineText" rows="7" placeholder="Monthly Bookkeeping&#10;  Collect Documents&#10;    Bank Statements" class="mt-2 w-full resize-y rounded-xl border border-slate-200 px-3 py-3 font-mono text-sm outline-none focus:border-blue-400"></textarea>
                    </label>
                    <div class="flex justify-end"><button type="button" @click="createTasksFromOutline()" :disabled="taskToolBusy || !taskOutlineText.trim()" class="h-10 rounded-lg bg-blue-600 px-5 text-xs font-semibold text-white disabled:opacity-50">Create task tree</button></div>
                    <div class="border-t border-slate-200 pt-4">
                        <h3 class="text-xs font-bold text-slate-800">Current outline</h3>
                        <div class="mt-3 max-h-72 space-y-1 overflow-y-auto rounded-xl border border-slate-200 p-2">
                            <template x-for="item in taskOutlineRows" :key="item.task.id">
                                <div class="flex items-center gap-2 rounded-lg px-2 py-2 hover:bg-slate-50" :style="`padding-left: ${8 + item.depth * 22}px`">
                                    <span class="min-w-0 flex-1 truncate text-xs font-medium text-slate-700" x-text="item.task.name"></span>
                                    <button type="button" @click="outdentTask(item)" :disabled="taskToolBusy || !item.task.parent_task_id" class="h-7 rounded border border-slate-200 px-2 text-[10px] text-slate-600 disabled:opacity-30">Outdent</button>
                                    <button type="button" @click="indentTask(item)" :disabled="taskToolBusy || !item.previous" class="h-7 rounded border border-slate-200 px-2 text-[10px] text-slate-600 disabled:opacity-30">Indent</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div x-show="showTaskModal"
         style="display: none;"
         class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-5 pointer-events-none"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-[0.98]"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-[0.98]">

        <div
            @click.stop
            class="pointer-events-auto flex max-h-[calc(100vh-32px)] w-full max-w-[920px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
        >

            <!-- =====================================================
                 HEADER
            ====================================================== -->
            <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-6 py-4 sm:px-7">
                <div class="min-w-0">
                    <h3
                        class="text-[17px] font-bold tracking-tight text-slate-900"
                        x-text="newTask.id ? 'Edit Task' : (newTask.parentTaskId ? 'SubTask' : 'New Task')"
                    >
                        New Task
                    </h3>
                </div>

                <button
                    type="button"
                    @click="showTaskModal = false"
                    class="ml-4 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Close"
                >
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>


            <!-- =====================================================
                 BODY
                 UI upgrade only.
                 New fields in this modal are intentionally not wired yet.
            ====================================================== -->
            <div class="custom-scrollbar flex-1 overflow-y-auto px-6 py-5 sm:px-7">

                <div class="grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">

                    <!-- Task Title -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Task Title <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="text"
                            x-model="newTask.name"
                            placeholder="What needs to be done?"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                    </div>


                    <!-- Related Account - UI ONLY -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Related Account
                        </label>

                        <div class="relative">
                            @include('partials.activity-relationships', ['relationshipForm' => 'newTask'])

                            <i class="far fa-building pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-300"></i>
                        </div>
                    </div>


                    <!-- Assigned To -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Assigned To (Multiple)
                        </label>

                        <div class="relative">
                            <select multiple x-model="newTask.assignedUserIds" class="min-h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100">
                                <template x-for="user in messageUserRecords" :key="'task-assignee-' + user.id"><option :value="String(user.id)" x-text="user.name || user.email"></option></template>
                            </select>

                            <i class="far fa-user pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-300"></i>
                        </div>
                    </div>


                    <!-- Priority -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Priority
                        </label>

                        <select
                            x-model="newTask.priority"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                            <option>Low</option>
                            <option>Normal</option>
                            <option>High</option>
                            <option>Urgent</option>
                        </select>
                    </div>


                    <!-- Start Date - UI ONLY -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Start Date
                        </label>

                        <input
                            type="date"
                            x-model="newTask.startDate"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                    </div>


                    <!-- Due Date - EXISTING BINDING -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Due Date
                        </label>

                        <input
                            type="date"
                            x-model="newTask.dueDate"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                    </div>


                    <!-- Status -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Status
                        </label>

                        <select
                            x-model="newTask.status"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                            <option>Open</option>
                            <option>In Progress</option>
                            <option>Waiting/On Hold</option>
                            <option>For Review</option>
                            <option>Completed</option>
                            <option>Cancelled</option>
                        </select>
                    </div>


                    <!-- Estimated Duration -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Estimated Duration
                        </label>

                        <div class="flex gap-2">
                            <input
                                type="number"
                                min="0"
                                x-model.number="newTask.estimatedDurationMinutes"
                                class="h-10 min-w-0 flex-1 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >

                            <select
                                class="h-10 w-[112px] shrink-0 rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                                <option selected>Minutes</option>
                                <option>Hours</option>
                                <option>Days</option>
                            </select>
                        </div>
                    </div>


                    <!-- Related To -->
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Related To (Link to Activity)
                        </label>

                        <div
                            class="relative"
                            @click.outside="closeRelatedPicker('tasks')"
                            @keydown.escape.window="closeRelatedPicker('tasks')"
                        >

                            <div class="flex min-h-[40px] flex-wrap items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 transition focus-within:border-[#1d54e2] focus-within:ring-2 focus-within:ring-blue-100">

                                <template x-for="tag in newTask.relatedTo" :key="tag">
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">
                                        <span
                                            class="max-w-[220px] truncate"
                                            x-text="tag"
                                        ></span>

                                        <button
                                            type="button"
                                            @click.stop="removeTag('tasks', tag)"
                                            class="text-blue-400 transition hover:text-blue-700"
                                        >
                                            <i class="fas fa-times text-[8px]"></i>
                                        </button>
                                    </span>
                                </template>

                                <input
                                    type="text"
                                    x-model="relatedSearch.tasks"
                                    @focus="openRelatedPicker('tasks')"
                                    @input="openRelatedPicker('tasks')"
                                    @keydown.enter.prevent="selectFirstRelatedOption('tasks')"
                                    @keydown.escape.stop.prevent="closeRelatedPicker('tasks')"
                                    placeholder="Meeting ID, Email, etc."
                                    class="min-w-[180px] flex-1 border-0 bg-transparent p-0 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-0 focus:ring-0"
                                >
                            </div>


                            <!-- Existing related-record dropdown -->
                            <div
                                x-show="relatedPickerOpen.tasks"
                                x-transition
                                style="display:none;"
                                class="relative z-20 mt-1.5 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
                            >

                                <div class="border-b border-slate-100 bg-slate-50 px-4 py-2">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">
                                        Select Related Record
                                    </p>
                                </div>

                                <div class="max-h-56 overflow-y-auto py-1">

                                    <template
                                        x-for="option in filteredRelatedOptions('tasks')"
                                        :key="option.key"
                                    >
                                        <button
                                            type="button"
                                            @click.stop="selectRelatedOption('tasks', option)"
                                            class="flex w-full items-start gap-3 px-4 py-2.5 text-left transition hover:bg-blue-50"
                                        >

                                            <span
                                                class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px]"
                                                :class="option.type === 'company'
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-blue-100 text-blue-700'"
                                            >
                                                <i
                                                    class="fas"
                                                    :class="option.type === 'company'
                                                        ? 'fa-building'
                                                        : 'fa-user'"
                                                ></i>
                                            </span>

                                            <span class="min-w-0 flex-1">

                                                <span
                                                    class="block truncate text-sm font-semibold text-slate-900"
                                                    x-text="option.label"
                                                ></span>

                                                <span
                                                    class="block truncate text-xs text-slate-500"
                                                    x-text="option.meta || (option.type === 'company'
                                                        ? 'Company record'
                                                        : 'Contact record')"
                                                ></span>

                                            </span>

                                        </button>
                                    </template>


                                    <div
                                        x-show="!filteredRelatedOptions('tasks').length"
                                        class="px-4 py-7 text-center"
                                    >
                                        <i class="fas fa-search mb-2 text-slate-300"></i>

                                        <p class="text-sm text-slate-500">
                                            No matching records found.
                                        </p>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>


                    <!-- Parent Task - UI ONLY -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Parent Task (Subtask of)
                        </label>

                        <select
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                            <option selected>&mdash;</option>
                        </select>
                    </div>


                    <!-- Depends On - UI ONLY -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Depends On (Blocker Task)
                        </label>

                        <select
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                            <option selected>None</option>
                        </select>
                    </div>


                    <!-- Recurrence - UI ONLY -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Recurrence
                        </label>

                        <select
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                            <option selected>Never</option>
                            <option>Daily</option>
                            <option>Weekly</option>
                            <option>Monthly</option>
                            <option>Yearly</option>
                            <option>Custom</option>
                        </select>
                    </div>


                    <!-- Reminder - UI ONLY -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Reminder
                        </label>

                        <select
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                            <option selected>No Reminder</option>
                            <option>At due time</option>
                            <option>5 minutes before</option>
                            <option>15 minutes before</option>
                            <option>30 minutes before</option>
                            <option>1 hour before</option>
                            <option>1 day before</option>
                        </select>
                    </div>


                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Description / Instructions
                        </label>

                        <textarea
                            rows="4"
                            x-model="newTask.description"
                            placeholder="Additional details..."
                            class="w-full resize-none rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm leading-5 text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        ></textarea>
                    </div>

                </div>
            </div>


            <!-- =====================================================
                 FOOTER
            ====================================================== -->
            <div class="shrink-0 border-t border-slate-100 bg-white px-6 py-3.5 sm:px-7">

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">

                    <button
                        type="button"
                        @click="showTaskModal = false"
                        class="h-9 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                    >
                        Cancel
                    </button>

                    <!-- UI ONLY -->
                    <button
                        type="button"
                        class="h-9 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
                    >
                        Duplicate
                    </button>

                    <!-- UI ONLY -->
                    <button
                        type="button"
                        class="h-9 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
                    >
                        Save as Template
                    </button>

                    <!-- Existing save action preserved -->
                    <button
                        type="button"
                        @click="saveTask()"
                        :disabled="!newTask.name || activitySaving.task"
                        class="h-9 rounded-md bg-[#1d54e2] px-5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1746bd] disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <span x-text="newTask.id ? 'Save Changes' : 'Save Task'"></span>
                    </button>

                </div>
            </div>

        </div>
    </div>
    <div
        x-show="showMeetingModal"
        x-cloak
        style="display: none;"
        class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-5 pointer-events-none"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-[0.98]"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-[0.98]"
    >

        <div
            @click.stop
            class="pointer-events-auto flex max-h-[calc(100vh-32px)] w-full max-w-[920px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
        >

            <!-- Header -->
            <div class="flex shrink-0 items-start justify-between border-b border-slate-100 bg-white px-6 py-4">

                <div>
                    <h3
                        class="text-lg font-bold text-slate-900"
                        x-text="newMeeting.id ? 'Edit Meeting' : 'Schedule New Meeting'"
                    >
                        Schedule New Meeting
                    </h3>
                </div>

                <button
                    type="button"
                    @click="showMeetingModal = false"
                    class="ml-4 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Close"
                >
                    <i class="fas fa-times text-sm"></i>
                </button>

            </div>

            <!-- Body -->
            <div class="custom-scrollbar flex-1 overflow-y-auto px-6 py-5">

                <div class="grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">

                    <!-- Meeting Title -->
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Meeting Title <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="text"
                            x-model="newMeeting.title"
                            placeholder="Enter or select previous title"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                    </div>


                    <!-- Meeting Type -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Meeting Type <span class="text-red-500">*</span>
                        </label>

                        <select
                            x-model="newMeeting.meetingType"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                            <option>Client Meeting</option>
                            <option>Internal Meeting</option>
                            <option>Board Meeting</option>
                            <option>Project Meeting</option>
                            <option>Coordination Meeting</option>
                            <option>Consultation</option>
                            <option>Other</option>
                        </select>
                    </div>


                    <!-- Meeting Mode -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Meeting Mode <span class="text-red-500">*</span>
                        </label>

                        <select
                            x-model="newMeeting.mode"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                            <option>Online</option>
                            <option>Physical</option>
                            <option>Hybrid</option>
                        </select>
                    </div>


                    <!-- Prepared By -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Prepared By
                        </label>

                        <div class="flex h-10 items-center rounded-md border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700" aria-readonly="true"><span x-text="currentUser || 'Authenticated user'"></span></div>
                    </div>


                    <!-- Related Account -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Related Account
                        </label>

                        @include('partials.activity-relationships', ['relationshipForm' => 'newMeeting'])
                    </div>


                    <!-- Related Project -->
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Related Project / Matter
                        </label>

                        <input
                            type="text"
                            x-model="newMeeting.relatedProject"
                            placeholder="Corporate Registration"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                    </div>


                    <!-- Internal Participants -->
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Internal Participants
                        </label>

                        <div class="flex min-h-[42px] flex-wrap items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2">

                            <template x-for="tag in newMeeting.internalParticipants" :key="tag">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-100 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">

                                    <span x-text="tag"></span>

                                    <button
                                        type="button"
                                        @click.stop="newMeeting.internalParticipants = newMeeting.internalParticipants.filter(x => x !== tag)"
                                        class="text-blue-400 hover:text-blue-700"
                                    >
                                        <i class="fas fa-times text-[9px]"></i>
                                    </button>
                                </span>
                            </template>

                            <select
                                @change="
                                    if ($event.target.value && !newMeeting.internalParticipants.includes($event.target.value)) {
                                        newMeeting.internalParticipants.push($event.target.value)
                                    }
                                    $event.target.value = ''
                                "
                                class="min-w-[180px] flex-1 border-none bg-transparent p-0 text-sm text-slate-700 outline-none focus:ring-0"
                            >
                                <option value="">Add employee...</option>

                                <template x-for="owner in systemUsers" :key="'internal-' + owner">
                                    <option :value="owner" x-text="owner"></option>
                                </template>
                            </select>

                        </div>
                    </div>


                    <!-- External Attendees -->
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            External Attendees
                        </label>

                        <input
                            type="text"
                            x-model="newMeeting.externalAttendees"
                            placeholder="Client Representative"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                    </div>


                    <!-- Date -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Date <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="date"
                            x-model="newMeeting.date"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                    </div>


                    <!-- Start Time -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Start Time <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="time"
                            x-model="newMeeting.time"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                    </div>


                    <!-- Estimated Duration -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Estimated Duration
                        </label>

                        <input
                            type="number"
                            min="0"
                            x-model.number="newMeeting.estimatedMinutes"
                            placeholder="90"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <div class="flex items-end pb-2 text-xs font-medium text-slate-500">
                        Minutes
                    </div>


                    <!-- Agenda -->
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                            Agenda / Purpose
                        </label>

                        <textarea
                            rows="4"
                            x-model="newMeeting.description"
                            placeholder="Review requirements, confirm next steps, and identify action items."
                            class="w-full resize-none rounded-md border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        ></textarea>
                    </div>

                </div>


                <!-- Calendar & Notifications -->
                <div class="mt-6 border-t border-slate-100 pt-5">

                    <h4 class="text-sm font-semibold text-slate-800">
                        Calendar &amp; Notifications
                    </h4>

                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Google Calendar is created automatically. Online and Hybrid meetings automatically create Google Meet and save the generated link to ORDO.
                    </p>

                    <div class="mt-4 space-y-3">

                        <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                x-model="newMeeting.addToCalendar"
                                class="h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                            >
                            <span>Add to JK&amp;C Google Calendar</span>
                        </label>

                        <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                x-model="newMeeting.createGoogleMeet"
                                class="h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                            >
                            <span>Create Google Meet automatically</span>
                        </label>

                        <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                x-model="newMeeting.notifyAttendees"
                                class="h-4 w-4 rounded border-slate-300 text-[#1d54e2] focus:ring-[#1d54e2]"
                            >
                            <span>Notify attendees by email <span class="text-xs text-slate-400">(OFF by default)</span></span>
                        </label>

                    </div>

                </div>

            </div>

            <!-- Footer -->
            <div class="shrink-0 border-t border-slate-100 bg-white px-6 py-4">

                <div class="flex items-center justify-end gap-3">

                    <button
                        type="button"
                        @click="showMeetingModal = false"
                        class="h-10 rounded-lg border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        @click="saveMeeting()"
                        :disabled="!newMeeting.title || !newMeeting.date || !newMeeting.time || activitySaving.meeting"
                        class="h-10 rounded-lg bg-[#1d54e2] px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1541b0] disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <span x-text="newMeeting.id ? 'Save Changes' : 'Save Meeting'"></span>
                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- Official Business Details Modal -->

    <!-- Unified Activity Details Popup -->

    <!-- Task Workspace -->

    <!-- Non-task Details Slide-over Panel -->

    <!-- Shared Event / Call / Meeting Detail Shell -->

    <!-- Meeting Details Slide-over Panel -->

    <!-- Video Player Slide-over -->
    <div x-show="showVideoPlayer"
         x-data="videoPlayer()"
         @keydown.escape.window="showVideoPlayer = false"
         style="display: none;"
         class="fixed top-0 bottom-0 right-0 z-[110] w-full max-w-[500px] transition-all"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-[0.97]"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-[0.97]">

        <div @click.stop class="pointer-events-auto bg-white shadow-2xl w-full max-w-[720px] max-h-[calc(100vh-48px)] flex flex-col overflow-hidden rounded-xl border border-gray-200">

            <!-- Header -->
            <div class="px-5 py-4 flex items-center justify-between shrink-0 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">Meeting Recording</h3>
                <button @click="showVideoPlayer = false" class="text-gray-400 hover:text-gray-600 transition text-lg leading-none">&times;</button>
            </div>

            <!-- Video Area -->
            <div class="flex-1 bg-gray-50 flex flex-col p-6">
                <div class="bg-black rounded-xl flex items-center justify-center overflow-hidden aspect-video shadow-lg mb-6 relative group">
                    <video x-ref="videoElement"
                           :src="videoSrc"
                           @timeupdate="updateTime()"
                           @loadedmetadata="updateTime()"
                           @ended="playing = false"
                           class="w-full h-full object-contain cursor-pointer"
                           @click="togglePlay()"></video>

                    <!-- Overlay when paused -->
                    <div x-show="!playing && currentTime === 0" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500 bg-black/50 pointer-events-none transition-opacity">
                        <i class="far fa-file-video text-7xl mb-4 text-white/70"></i>
                        <p class="text-xs font-medium uppercase tracking-widest text-white/90">Video Stream Ready</p>
                    </div>
                </div>

                <!-- Playback Controls (Standardized) -->
                <div class="bg-[#1d54e2] rounded-xl px-6 py-4 flex flex-col shadow-md">
                    <div class="flex items-center gap-4 mb-3">
                        <button @click="togglePlay()" class="text-white hover:text-gray-200 transition">
                            <i :class="['fas text-base', playing ? 'fa-pause' : 'fa-play']"></i>
                        </button>
                        <div class="flex-1 h-1.5 bg-blue-400/30 rounded-full cursor-pointer relative" @click="seek($event)">
                            <div class="absolute left-0 top-0 h-full bg-white rounded-full transition-all duration-75" :style="`width: ${duration ? (currentTime / duration) * 100 : 0}%`"></div>
                        </div>
                        <span class="text-white text-xs font-bold font-mono" x-text="`${formatTime(currentTime)} / ${formatTime(duration)}`">00:00 / 00:00</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <button @click="skip(-10)" class="text-blue-100 hover:text-white transition" title="Rewind 10s"><i class="fas fa-undo text-sm"></i></button>
                            <button @click="skip(10)" class="text-blue-100 hover:text-white transition" title="Skip 10s"><i class="fas fa-redo text-sm"></i></button>
                        </div>
                        <div class="flex items-center gap-4">
                            <button @click="toggleMute()" class="text-blue-100 hover:text-white transition" title="Toggle Mute">
                                <i :class="['fas text-sm', muted ? 'fa-volume-mute text-blue-200' : 'fa-volume-up']"></i>
                            </button>
                            <button @click="toggleFullScreen()" class="text-blue-100 hover:text-white transition" title="Fullscreen">
                                <i class="fas fa-expand text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-8 space-y-4">
                    <h4 class="text-sm font-bold text-gray-900 border-l-4 border-[#1d54e2] pl-3">Recording Details</h4>
                    <div class="grid grid-cols-1 gap-4">
                        <div class="bg-white p-3 rounded-lg border border-gray-100 shadow-sm">
                            <p class="text-[10px] text-gray-400 uppercase font-black mb-1">Duration</p>
                            <p class="text-sm font-bold text-gray-700" x-text="formatDurationText(duration)">45 Minutes</p>
                        </div>
                    </div>

                    {{-- Upload Minutes Action --}}
                    <div class="mt-4">
                        <input type="file" x-ref="minutesInputPlayer" @change="uploadMinutes(selectedMeetingDetails.id, $event)" class="hidden" accept=".pdf,.doc,.docx,.txt" />
                        <button @click="$refs.minutesInputPlayer.click()"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-50 border border-gray-200 text-gray-700 rounded-xl text-xs font-bold hover:bg-white hover:border-[#1d54e2] hover:text-[#1d54e2] transition shadow-sm group">
                            <i class="fas fa-file-upload text-blue-500 group-hover:scale-110 transition-transform"></i>
                            Upload Meeting Minutes
                        </button>
                        <div x-show="isUploadingMinutes" class="mt-2 px-1">
                            <div class="w-full bg-gray-200 rounded-full h-1 overflow-hidden">
                                <div class="bg-blue-600 h-1 animate-pulse"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <!-- Footer -->
            <div class="px-5 py-4 flex items-center justify-between border-t border-gray-100 shrink-0">
                <div class="flex items-center gap-2">
                    <!-- Transcript Dropdown -->
                    <template x-if="selectedMeetingDetails?.has_transcript">
                        <div class="relative">
                            <button @click="activeDownloadMenu = activeDownloadMenu === 'player_transcript' ? null : 'player_transcript'"
                                    class="px-3 py-2 rounded-lg border border-gray-200 text-gray-700 bg-gray-50 hover:bg-white hover:border-green-500 hover:text-green-600 text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                                <i class="fas fa-file-alt text-green-500"></i>
                                Transcript
                            </button>
                            <div x-show="activeDownloadMenu === 'player_transcript'"
                                 @click.away="activeDownloadMenu = null"
                                 class="absolute bottom-full left-0 mb-2 w-32 bg-white rounded-lg shadow-xl border border-gray-100 z-[120] overflow-hidden"
                                 style="display: none;">
                                <button @click="downloadFile('transcript', 'pdf'); activeDownloadMenu = null" class="w-full text-left px-4 py-2 text-[10px] text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                    <i class="far fa-file-pdf text-red-500"></i> PDF (.pdf)
                                </button>
                                <button @click="downloadFile('transcript', 'docs'); activeDownloadMenu = null" class="w-full text-left px-4 py-2 text-[10px] text-gray-700 hover:bg-gray-50 flex items-center gap-2 border-t border-gray-50">
                                    <i class="far fa-file-word text-blue-500"></i> DOCS (.docx)
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Minutes Dropdown -->
                    <template x-if="selectedMeetingDetails?.has_minutes">
                        <div class="relative">
                            <button @click="activeDownloadMenu = activeDownloadMenu === 'player_minutes' ? null : 'player_minutes'"
                                    class="px-3 py-2 rounded-lg border border-gray-200 text-gray-700 bg-gray-50 hover:bg-white hover:border-purple-500 hover:text-purple-600 text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                                <i class="fas fa-file-signature text-purple-500"></i>
                                Minutes
                            </button>
                            <div x-show="activeDownloadMenu === 'player_minutes'"
                                 @click.away="activeDownloadMenu = null"
                                 class="absolute bottom-full left-0 mb-2 w-32 bg-white rounded-lg shadow-xl border border-gray-100 z-[120] overflow-hidden"
                                 style="display: none;">
                                <button @click="downloadFile('minutes', 'pdf'); activeDownloadMenu = null" class="w-full text-left px-4 py-2 text-[10px] text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                    <i class="far fa-file-pdf text-red-500"></i> PDF (.pdf)
                                </button>
                                <button @click="downloadFile('minutes', 'docs'); activeDownloadMenu = null" class="w-full text-left px-4 py-2 text-[10px] text-gray-700 hover:bg-gray-50 flex items-center gap-2 border-t border-gray-50">
                                    <i class="far fa-file-word text-blue-500"></i> DOCS (.docx)
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
                <button @click="showVideoPlayer = false" class="px-6 py-2 rounded-md bg-[#1d54e2] text-white hover:bg-[#1541b0] text-sm font-semibold transition shadow-sm">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================
         NEW / EDIT CALL MODAL
    ========================================================== -->
    <div
        x-show="showCallModal"
        x-cloak
        style="display: none;"
        class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-5 pointer-events-none"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-[0.98]"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-[0.98]"
    >

        <div
            @click.stop
            class="pointer-events-auto flex max-h-[calc(100vh-30px)] w-full max-w-[900px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
        >

            <!-- Header -->
            <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-5 py-4">

                <div>
                    <h3
                        class="text-lg font-bold text-slate-900"
                        x-text="newCall.id ? 'Edit Call' : 'New Call'"
                    >
                        New Call
                    </h3>
                </div>

                <button
                    type="button"
                    @click="showCallModal = false"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Close"
                >
                    <i class="fas fa-times text-sm"></i>
                </button>

            </div>


            <!-- Scrollable Body -->
            <div class="flex-1 overflow-y-auto custom-scrollbar px-5 py-4">

                <div class="space-y-4">


                    <!-- =====================================================
                         CALL INFORMATION
                    ====================================================== -->
                    <section class="rounded-xl border border-slate-200 bg-white p-4">

                        <h4 class="mb-3 text-xs font-bold text-slate-700">
                            Call Information
                        </h4>
                        @include('partials.activity-relationships', ['relationshipForm' => 'newCall'])

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                            <!-- Call Title -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Call Title <span class="text-red-500">*</span>
                                </label>

                                <input
                                    type="text"
                                    x-model="newCall.purpose"
                                    placeholder="e.g. SEC Requirements Follow-Up"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                            </div>


                            <!-- Call Type -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Call Type <span class="text-red-500">*</span>
                                </label>

                                <select
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                                    <option>Client Call</option>
                                    <option>Internal Call</option>
                                    <option>Vendor / Supplier Call</option>
                                    <option>Government / Agency Call</option>
                                    <option>Bank / Financial Institution</option>
                                    <option>Other</option>
                                </select>
                            </div>


                            <!-- Related Account -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Related Account
                                </label>

                                <input
                                    type="text"
                                    placeholder="ABC Holdings Inc."
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                            </div>


                            <!-- Related Project / Matter -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Related Project / Matter
                                </label>

                                <input
                                    type="text"
                                    placeholder="Corporate Registration"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                            </div>


                            <!-- Direction -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Direction <span class="text-red-500">*</span>
                                </label>

                                <select
                                    x-model="newCall.type"
                                    @change="handleCallDirection(newCall.type)"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                                    <option value="Outbound">Outgoing</option>
                                    <option value="Inbound">Incoming</option>
                                </select>
                            </div>


                            <!-- Prepared By -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Prepared By
                                </label>

                                <input
                                    type="text"
                                    :value="newCall.owner || currentUser || ''"
                                    readonly
                                    placeholder="John Kelly D. Abalde"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700 outline-none"
                                >
                            </div>


                            <!-- Date -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Date <span class="text-red-500">*</span>
                                </label>

                                <input
                                    type="date"
                                    x-model="newCall.startTime"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                            </div>


                            <!-- Start Time -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Start Time <span class="text-red-500">*</span>
                                </label>

                                <input
                                    type="time"
                                    x-model="newCall.startHour"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                            </div>


                            <!-- Estimated Duration -->
                            <div class="md:col-span-2">
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Estimated Duration
                                </label>

                                <div class="grid grid-cols-[1fr_120px] gap-2">

                                    <input
                                        type="number"
                                        min="0"
                                        x-model="newCall.duration"
                                        placeholder="15"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                    >

                                    <select
                                        class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option>Minutes</option>
                                        <option>Hours</option>
                                    </select>

                                </div>
                            </div>

                        </div>

                    </section>


                    <!-- =====================================================
                         CALL PARTIES
                    ====================================================== -->
                    <section class="rounded-xl border border-slate-200 bg-white p-4">

                        <h4 class="mb-3 text-xs font-bold text-slate-700">
                            Call Parties
                        </h4>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                            <!-- Initiator Type -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Caller / Initiator <span class="text-red-500">*</span>
                                </label>

                                <select
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                                    <option>JK&amp;C Employee</option>
                                    <option>Client</option>
                                    <option>External Contact</option>
                                </select>
                            </div>


                            <!-- Initiator Name -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Caller / Initiator Name <span class="text-red-500">*</span>
                                </label>

                                <input
                                    type="text"
                                    x-model="newCall.from"
                                    placeholder="John Kelly D. Abalde"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                            </div>


                            <!-- Internal Participants -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Internal Participants
                                </label>

                                <input
                                    type="text"
                                    placeholder="John Kelly D. Abalde, Michael Angelo C. Labrador"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >

                                <p class="mt-1.5 text-[10px] text-slate-400">
                                    Select existing ORDO employees in production.
                                </p>
                            </div>


                            <!-- External Participants -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    External Participants / Other Side
                                </label>

                                <input
                                    type="text"
                                    x-model="newCall.to"
                                    placeholder="Client Representative"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >

                                <p class="mt-1.5 text-[10px] text-slate-400">
                                    Multiple contacts allowed for group calls.
                                </p>
                            </div>


                            <div class="md:col-span-2">

                                <p class="text-[10px] text-slate-400">
                                    Group Call is detected automatically when more than two participants are recorded.
                                </p>

                            </div>

                        </div>

                    </section>


                    <!-- =====================================================
                         CALL CHANNEL & DEVICE
                    ====================================================== -->
                    <section class="rounded-xl border border-slate-200 bg-white p-4">

                        <h4 class="mb-3 text-xs font-bold text-slate-700">
                            Call Channel &amp; Device
                        </h4>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                            <!-- Channel -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Call Channel
                                </label>

                                <select
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                                    <option>Mobile Call</option>
                                    <option>Landline</option>
                                    <option>Teams Call</option>
                                    <option>Messenger Call</option>
                                    <option>Viber Call</option>
                                    <option>WhatsApp Call</option>
                                    <option>Other</option>
                                </select>
                            </div>


                            <!-- Device -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Phone / Device Used
                                </label>

                                <select
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                                    <option>John Kelly &mdash; iPhone</option>
                                    <option>Office Mobile Phone</option>
                                    <option>Office Landline</option>
                                    <option>Personal Device</option>
                                </select>
                            </div>


                            <!-- SIM / Line -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    SIM / Line Used
                                </label>

                                <select
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                                    <option>Globe &mdash; JK&amp;C Main</option>
                                    <option>Smart &mdash; JK&amp;C Backup</option>
                                    <option>Office Landline</option>
                                    <option>Other</option>
                                </select>
                            </div>


                            <!-- Number -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                                    Number / Account Used
                                </label>

                                <input
                                    type="text"
                                    placeholder="0917 XXX XXXX"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                                >
                            </div>

                        </div>

                    </section>


                    <!-- =====================================================
                         PURPOSE / AGENDA
                    ====================================================== -->
                    <section>

                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">
                            Purpose / Agenda
                        </label>

                        <textarea
                            rows="4"
                            x-model="newCall.agenda"
                            placeholder="Clarify outstanding requirements and confirm next steps."
                            class="w-full resize-none rounded-md border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                        ></textarea>

                    </section>

                </div>

            </div>


            <!-- Footer -->
            <div class="shrink-0 border-t border-slate-100 bg-white px-5 py-4">

                <div class="flex items-center justify-end gap-2">

                    <button
                        type="button"
                        @click="showCallModal = false"
                        class="h-9 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        @click="saveCall()"
                        :disabled="activitySaving.call"
                        class="h-9 rounded-md bg-[#1d54e2] px-5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1541b0] disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <span x-text="newCall.id ? 'Save Changes' : 'Save Call'"></span>
                    </button>

                </div>

            </div>

        </div>

    </div>
    <div
    x-show="showEventModal"
    x-cloak
    style="display: none;"
    class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-5 pointer-events-none"
    x-transition:enter="ease-out duration-300"
    x-transition:enter-start="opacity-0 scale-[0.98]"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-[0.98]"
>
    <div
        @click.stop
        class="pointer-events-auto flex max-h-[calc(100vh-32px)] w-full max-w-[980px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
    >

        <!-- =====================================================
             HEADER
        ====================================================== -->
        <div class="flex shrink-0 items-start justify-between border-b border-slate-100 bg-white px-5 py-4 sm:px-6">
            <div>
                <h3 class="text-lg font-bold text-slate-900">
                    New Event
                </h3>

                <p class="mt-1 text-xs text-slate-500">
                    Plan and document an organizational event or activity.
                </p>
            </div>

            <button
                type="button"
                @click="showEventModal = false"
                class="ml-4 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                aria-label="Close"
            >
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>


        <!-- =====================================================
             BODY
        ====================================================== -->
        <div class="custom-scrollbar flex-1 overflow-y-auto bg-slate-50/50 px-5 py-5 sm:px-6">

            <div class="space-y-4">

                <!-- =================================================
                     ORGANIZER
                ================================================== -->
                <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <h4 class="mb-4 text-xs font-bold text-slate-800">
                        Organizer
                    </h4>
                    @include('partials.activity-relationships', ['relationshipForm' => 'newEvent'])

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                        <!-- Organizer Type -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Organizer Type
                            </label>

                            <select
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                                <option selected>JK&amp;C</option>
                                <option>Client</option>
                                <option>External Organization</option>
                                <option>Joint Organizer</option>
                            </select>
                        </div>

                        <!-- Organizer Name -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Organizer Name
                            </label>

                            <input
                                type="text"
                                x-model="newEvent.host"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- Description -->
                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Description / Purpose
                            </label>

                            <textarea
                                rows="4"
                                x-model="newEvent.title"
                                class="w-full resize-none rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm leading-6 text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            ></textarea>
                        </div>

                    </div>
                </section>


                <!-- =================================================
                     SCHEDULE & VENUE
                ================================================== -->
                <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <h4 class="mb-4 text-xs font-bold text-slate-800">
                        Schedule &amp; Venue
                    </h4>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                        <!-- Start Date -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Start Date
                            </label>

                            <input
                                type="date"
                                x-model="newEvent.fromDate" @change="if (!newEvent.toDate || newEvent.toDate < newEvent.fromDate) newEvent.toDate = newEvent.fromDate"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- Start Time -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Start Time
                            </label>

                            <input
                                type="time"
                                x-model="newEvent.fromTime"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- End Date -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                End Date
                            </label>

                            <input
                                type="date"
                                x-model="newEvent.toDate" :min="newEvent.fromDate || ''"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- End Time -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                End Time
                            </label>

                            <input
                                type="time"
                                x-model="newEvent.toTime"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- Event Mode -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Event Mode
                            </label>

                            <select
                                x-model="newEvent.mode"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                                <option>Physical</option>
                                <option>Online</option>
                                <option>Hybrid</option>
                            </select>
                        </div>

                        <!-- Venue -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Venue Name
                            </label>

                            <input
                                type="text"
                                x-model="newEvent.venue"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- Address -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Address / Location
                            </label>

                            <input
                                type="text"
                                value="Cebu City, Cebu"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- Room -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Room / Hall / Area
                            </label>

                            <input
                                type="text"
                                value="Main Hall"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                    </div>

                    <p class="mt-3 text-[10px] text-slate-400">
                        Estimated Duration is automatically computed from the planned Start and End schedule.
                    </p>
                </section>


                <!-- =================================================
                     TRAINING INFORMATION
                ================================================== -->
                <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <h4 class="mb-4 text-xs font-bold text-slate-800">
                        Training Information
                    </h4>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                        <!-- Provider Type -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Training Provider Type
                            </label>

                            <select
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                                <option selected>JK&amp;C</option>
                                <option>Client</option>
                                <option>External Provider</option>
                                <option>Joint Provider</option>
                            </select>
                        </div>

                        <!-- Provider -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Training Provider
                            </label>

                            <input
                                type="text"
                                value="John Kelly & Company"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- Trainer -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Trainer / Facilitator
                            </label>

                            <input
                                type="text"
                                value="John Kelly D. Abalde"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- Target -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Target Participants
                            </label>

                            <input
                                type="text"
                                value="MSME owners, managers, and administrative personnel"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- Expected -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Expected Participants
                            </label>

                            <input
                                type="number"
                                min="0"
                                x-model.number="newEvent.participants"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- Certificates -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Certificates Planned
                            </label>

                            <input
                                type="number"
                                min="0"
                                value="40"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- Learning Objectives -->
                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Learning Objectives
                            </label>

                            <textarea
                                rows="4"
                                class="w-full resize-none rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm leading-6 text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >Introduce practical administrative systems and show participants how structured workflows can improve business operations and documentation.</textarea>
                        </div>

                    </div>
                </section>


                <!-- =================================================
                     PARTICIPANTS & ATTENDANCE
                ================================================== -->
                <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                    <h4 class="mb-4 text-xs font-bold text-slate-800">
                        Participants &amp; Attendance Setup
                    </h4>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                        <!-- Internal Participants -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Internal Participants
                            </label>

                            <input
                                type="text"
                                value="John Kelly D. Abalde, Michael Angelo C. Labrador"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >

                            <p class="mt-1.5 text-[10px] text-slate-400">
                                Use ORDO employee multi-select in production.
                            </p>
                        </div>

                        <!-- External Participants -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Named External Participants
                            </label>

                            <input
                                type="text"
                                value="Client Representative"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >

                            <p class="mt-1.5 text-[10px] text-slate-400">
                                Use contacts for smaller events; attendance counts can handle large events.
                            </p>
                        </div>

                        <!-- Expected Attendance -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Expected Attendance
                            </label>

                            <input
                                type="number"
                                min="0"
                                value="40"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                        <!-- Registered -->
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-slate-600">
                                Registered / Confirmed
                            </label>

                            <input
                                type="number"
                                min="0"
                                value="35"
                                class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-[#1d54e2] focus:ring-2 focus:ring-blue-100"
                            >
                        </div>

                    </div>
                </section>

            </div>
        </div>


        <!-- =====================================================
             FOOTER
        ====================================================== -->
        <div class="shrink-0 border-t border-slate-100 bg-white px-5 py-4 sm:px-6">
            <div class="flex items-center justify-end gap-2">

                <button
                    type="button"
                    @click="showEventModal = false"
                    class="h-9 rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                >
                    Cancel
                </button>

                <!-- UI ONLY - wire after the Event data model is upgraded -->
                <button
                    type="button"
                    @click="saveEvent()"
                    :disabled="!newEvent.title || activitySaving.event"
                    class="h-9 rounded-md bg-[#1d54e2] px-5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1541b0]"
                >
                    Save Event
                </button>

            </div>
        </div>

    </div>
</div>
<!-- Export Slide-over -->
    <div x-show="showExportModal"
         style="display: none;"
         class="fixed top-0 bottom-0 right-0 z-[100] w-full max-w-[420px] transition-all"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-[0.97]"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-[0.97]">

        <div @click.stop class="pointer-events-auto bg-white shadow-2xl w-full max-w-[720px] max-h-[calc(100vh-48px)] flex flex-col overflow-hidden rounded-xl border border-gray-200">

            <!-- Header -->
            <div class="px-5 py-4 flex items-center justify-between shrink-0 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">Export Data</h3>
                <button @click="showExportModal = false" class="text-gray-400 hover:text-gray-600 transition text-lg leading-none">&times;</button>
            </div>

            <!-- Body -->
            <div class="px-5 py-4 space-y-6 overflow-y-auto custom-scrollbar flex-1">
                <div class="space-y-1">
                    <p class="text-sm font-bold text-gray-900">Start an Export</p>
                    <p class="text-xs text-gray-500">Choose the fields you would like to include in your export file.</p>
                </div>

                <div class="bg-blue-50 rounded-xl p-5 border border-blue-100 text-xs text-blue-800">
                    <span x-show="selectedActivities.length" x-text="`${selectedActivities.length} selected activities will be exported.`"></span>
                    <span x-show="!selectedActivities.length">The current server-side account, date, type, status, and duration filters will be exported.</span>
                </div>

                <div x-show="activeTab === 'official-business'" class="space-y-3">
                    <p class="text-xs font-semibold text-gray-800">O.B. report filters</p>
                    <div class="grid grid-cols-2 gap-2">
                        <input x-model="obReportMonth" type="month" aria-label="O.B. report month" class="input" title="Month">
                        <input x-model="obReportReference" type="text" placeholder="O.B. number" class="input">
                        <input x-model="obReportTicketReference" type="text" placeholder="Trip Ticket number" class="input">
                        <input x-model="obReportDestination" type="text" placeholder="Destination" class="input">
                        <input x-model="obReportActivity" type="text" placeholder="Activity" class="input">
                        <input x-model="obReportTransportation" type="text" placeholder="Transportation" class="input">
                        <input x-model="obReportEmployee" type="text" placeholder="Personnel" class="input">
                        <input x-model="obReportDepartment" type="text" placeholder="Department" class="input">
                        <template x-if="officialBusinessCapabilities?.finance">
                            <div class="col-span-2 grid grid-cols-2 gap-2">
                                <select x-model="obReportCredit" aria-label="Travel credit use" class="input">
                                    <option value="">Any travel credit</option><option value="with">Credit used</option><option value="without">No credit used</option>
                                </select>
                                <select x-model="obReportCost" aria-label="Additional cost" class="input">
                                    <option value="">Any cost</option><option value="with">With cost</option><option value="without">Without cost</option>
                                </select>
                                <input x-model="obReportBillability" type="text" placeholder="Billability" class="input col-span-2">
                            </div>
                        </template>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                    <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-3">Export Format</p>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="format in exportFormats" :key="format.value">
                            <button type="button" @click="exportFormat = format.value" :class="exportFormat === format.value ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white'" class="rounded-lg border-2 p-3 text-left transition">
                                <i :class="format.icon" class="text-blue-500"></i>
                                <p class="mt-2 text-xs font-bold text-gray-800" x-text="format.label"></p>
                            </button>
                        </template>
                    </div>
                </div>
                <p x-show="exportError" x-text="exportError" class="text-xs font-medium text-red-600"></p>
            </div>

            <!-- Footer -->
            <div class="px-5 py-4 flex items-center justify-between border-t border-gray-100 shrink-0">
                <button @click="showExportModal = false" class="px-6 py-2 rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 text-sm font-medium transition">
                    Cancel
                </button>
                <button @click="exportData()" :disabled="exportLoading" class="px-6 py-2 rounded-md bg-[#1d54e2] text-white hover:bg-[#1541b0] disabled:cursor-not-allowed disabled:opacity-60 text-sm font-semibold transition shadow-sm">
                    <span x-text="exportLoading ? 'Exporting...' : 'Export Now'"></span>
                </button>
            </div>
        </div>
        </div>

    </div><!-- end main content -->

<script>

window.activitiesData = () => ({

        showTaskModal: false,
        activeTab: 'all',
        activityDetailOrigin: null,
        activityDetailRequest: 0,
        get hasActivityDetail() {
            return !!(this.selectedTaskDetails || this.selectedCoreActivityDetails ||
                this.selectedMeetingDetails || this.selectedOfficialBusinessDetails ||
                this.selectedActivityDetails || this.showMessageDetails || this.visitDetailsOpen);
        },
        get activityDetailBackLabel() {
            return this.activityDetailOrigin?.label || 'Back to Activities';
        },
        beginActivityDetail() {
            if (!this.activityDetailOrigin) {
                const labels = { all: 'All Activities', events: 'Events', call: 'Calls',
                    meetings: 'Meetings', emails: 'Emails', messages: 'Messages',
                    'official-business': 'Official Business', 'visitor-log': 'Visitor Log' };
                const label = this.activeTab === 'task'
                    ? (this.taskView === 'list' ? this.filterSelection.task : this.taskView.charAt(0).toUpperCase() + this.taskView.slice(1))
                    : (labels[this.activeTab] || 'Activities');
                this.activityDetailOrigin = { label: 'Back to ' + label, scrollTop: this.$refs.activitiesMain?.scrollTop || 0 };
            }
            this.clearActivityDetailSelection();
            this.$nextTick(() => { if (this.$refs.activitiesMain) this.$refs.activitiesMain.scrollTop = 0; });
            return ++this.activityDetailRequest;
        },
        clearActivityDetailSelection() {
            this.resetMessageDraft();
            this.closeTaskWorkspace();
            this.closeCoreActivityDetails();
            this.closeOfficialBusinessDetails();
            this.closeActivityDetails();
            this.selectedMeetingDetails = null;
            this.showMessageDetails = false;
            this.selectedMessageDetails = null;
            this.visitDetailsOpen = false;
            this.selectedVisitDetails = null;
            this.showVideoPlayer = false;
        },
        returnToActivities() {
            ++this.activityDetailRequest;
            this.clearActivityDetailSelection();
            this.restoreActivityListPosition();
        },
        restoreActivityListPosition() {
            const origin = this.activityDetailOrigin;
            this.activityDetailOrigin = null;
            if (origin) this.$nextTick(() => {
                if (this.$refs.activitiesMain) this.$refs.activitiesMain.scrollTop = origin.scrollTop;
            });
        },
        tasksExpanded: false,
        get activitySidebarGroup() {
            if (this.activeTab === 'all') return 'all';
            if (this.activeTab !== 'task') return '';
            return 'tasks';
        },
        expandActiveSidebarGroup() {
            if (this.activitySidebarGroup === 'tasks') this.tasksExpanded = true;
        },
        visitorLogTab: 'front-desk',
        visitorLogExpanded: true,
        visitorSearch: '',
        visitorSearchResults: [],
        selectedVisitor: null,
        visitorSearchLoading: false,
        visitorLogLoading: false,
        visitorLogError: '',
        currentVisitorSearch: '',
        visitorBranchId: '',
        visitorActivityType: '',
        visitorActivityId: '',
        currentVisits: [],
        visitRecords: [],
        visitRecordSearch: '',
        visitRecordStatus: '',
        visitRecordFilterOpen: false,
        firstTimeVisitorOpen: false,
        firstTimeVisitorSaving: false,
        activitySaving: { task: false, event: false, call: false, meeting: false },
        newVisitor: { name: '', mobile: '', email: '', company_name: '' },
        visitDetailsOpen: false,
        selectedVisitDetails: null,
        officialBusinessTab: 'all',

        deletedObSearch: '',

        deletedOfficialBusinessRecords: [],

        travelDeskTab: 'all',
        travelDeskSearch: '',

        travelDeskRecords: [],
        myObStatusTab: 'all',
        myObSearch: '',
        myObStatusFilter: 'all',
        myObClientFilter: 'all',
        myObTypeFilter: 'all',
        myObDateFrom: '',
        myObDateTo: '',

        obSearch: '',
        obStatusFilter: 'all',
        obEmployeeFilter: 'all',
        obClientFilter: 'all',
        obDepartmentFilter: 'all',
        obTypeFilter: 'all',
        obDateFrom: '',
        obDateTo: '',
        obReportMonth: '',
        obReportReference: '',
        obReportTicketReference: '',
        obReportDestination: '',
        obReportActivity: '',
        obReportTransportation: '',
        obReportEmployee: '',
        obReportDepartment: '',
        obReportCredit: '',
        obReportCost: '',
        obReportBillability: '',

        officialBusinessRecords: [],
        officialBusinessLoading: false,
        officialBusinessRequestError: '',
        officialBusinessWorkspaceUrl: @json(route('human-capital.obf')),
        officialBusinessApprovalUrlTemplates: {
            approve: @json(route('human-capital.obf.approve', ['officialBusinessTrip' => '__OFFICIAL_BUSINESS_TRIP__'])),
            revert: @json(route('human-capital.obf.revert', ['officialBusinessTrip' => '__OFFICIAL_BUSINESS_TRIP__'])),
        },
        officialBusinessCapabilities: {},
        officialBusinessCanManage: false,
        selectedOfficialBusinessDetails: null,
        officialBusinessDetailTab: 'overview',
        officialBusinessRelatedActivities: [],
        officialBusinessAuditLog: [],
        officialBusinessDetailLoading: false,
        selectAll: false,
        selectedTasks: [],
        taskToolModal: null,
        taskToolBusy: false,
        taskBulkStatus: '',
        taskBulkPriority: '',
        taskOutlineText: '',
        taskTemplates: [],
        taskTemplatesLoading: false,
        draggedTaskId: null,
        tasks: [],
        taskView: 'list',
        timelineScope: 'all',
        calendarDate: new Date(),
        calendarSelectedDate: null,
        calendarDayModalOpen: false,
        calendarWeekdays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        taskSearch: '',
        taskStatusFilter: 'all',
        taskPriorityFilter: 'all',
        taskOwnerFilter: 'all',
        currentUser: '',
        currentUserId: null,
        teamUsers: [],
        systemUsers: [],
        messageUserRecords: [],
        contacts: [],
        relatedOptions: [],
        relatedRecords: [],
        unifiedActivities: [],
        allActivitiesReportRows: [],
        allActivitiesReportCoverage: null,
        allActivitiesReportLoading: false,
        allActivitiesReportError: '',
        allActivitiesReportRequest: 0,
        allActivitiesSearch: '',
        allActivitiesAccountFilter: '',
        allActivitiesStatusFilter: '',
        allActivitiesDateFilter: '',
        allActivitiesMonthFilter: '',
        allActivitiesDateFrom: '',
        allActivitiesDateTo: '',
        allActivitiesTypeFilters: [],
        allActivitiesDurationFilter: '',
        selectedActivities: [],
        relatedSearch: { tasks: '', events: '', calls: '', meetings: '' },
        relatedPickerOpen: { tasks: false, events: false, calls: false, meetings: false },
        newTask: { id: null, parentTaskId: null, name: '', dueDate: '', startDate: '', estimatedDurationMinutes: '', recurrence: '', reminderType: '', reminderValue: '', dependencyTaskIds: [], relatedAccount: '', companyId: null, contactId: null, assignedUserIds: [], relatedTo: [], description: '', priority: 'Normal', status: 'Open', completed: false, owner: '' },

        events: [],
        newEvent: { companyId: '', contactId: '',
    title: '',
    related_account: '',
    event_type: '',
    from: '',
    to: '',
    fromDate: '',
    fromTime: '',
    toDate: '',
    toTime: '',
    venue: '',
    mode: 'Physical',
    participants: '',
    role: '',
    status: 'Scheduled',
    estimated_hours: '',
    actual_hours: '',
    relatedTo: [],
    host: ''
},

        eventViewTab: 'all',
        eventSearch: '',
        eventModeFilter: 'all',
        eventStatusFilter: 'all',

        calls: [],
        newCall: { companyId: '', contactId: '', contact: '', to: '', from: '', manualFrom: '', type: 'Outbound', startTime: '', startHour: '', duration: '', relatedTo: [], owner: '', agenda: '', purpose: '', status: '' },

        meetings: [],

        emails: [],
        emailMailboxes: [],

        emailTab: 'all',

        emailSearch: '',

        emailClientFilter: 'all',

        emailHandlingFilter: 'all',

        showComposeEmail: false,

        emailComposeNotice: '',

        newEmail: { companyId: '', contactId: '',
            subject: '',
            client: '',
            contact: '',
            fromMailbox: '',
            to: '',
            cc: '',
            bcc: '',
            body: '',
            threadId: '',
            inReplyTo: '',
            referencesHeader: '',
            replyToId: null,
            direction: 'Outgoing',
            assignedTo: ''
        },
        emailAttachmentFiles: [],
        messages: [],

        messageTab: 'messenger',

        messageSearch: '',

        messageClientFilter: 'all',

        messageHandlingFilter: 'all',

        messageLoading: false,

        messageSaving: false,

        messageRequestError: '',

        showMessageDetails: false,

        selectedMessageDetails: null, messageRelationships: { companyId: '', contactId: '' },

        newMessageEntry: { body: '' },
        messageAttachments: [],
        messageDraftAttachmentIds: [],
        messageDraftSession: 0,
        messageSendRequest: null,
        messageAttachmentLoadRequest: 0,
        messageAttachmentLoading: false,
        messageAttachmentUploading: false,

        showNewMessage: false,

        newMessage: { companyId: '', contactId: '',
            conversationTitle: '',
            conversationSummary: '',
            client: '',
            contact: '',
            channel: 'SMS',
            lineAccount: '',
            recipient: '',
            body: '',
            direction: 'Conversation Thread',
            durationMinutes: '',
            handling: 'Open',
            assignedTo: ''
        },
        newMeeting: {
            id: null,
            title: '',
            meetingType: 'Client Meeting',
            relatedAccount: '',
            companyId: null, contactId: null,
            relatedProject: '',
            mode: 'Online',
            owner: '',
            date: '',
            time: '',
            duration: '',
            durationHour: '1',
            durationMin: '30',
            estimatedMinutes: 90,
            actualDuration: '',
            location: '',
            attendees: [],
            internalParticipants: [],
            externalAttendees: '',
            description: '',
            addToCalendar: true,
            createGoogleMeet: true,
            notifyAttendees: false,
            hasVideo: false,
            hasAudio: false,
            hasTranscript: false,
            hasMinutes: false,
            status: 'upcoming'
        },

        filterSelection: {
            task: 'All Tasks',
            events: 'All Events',
            call: 'All Calls'
        },
        selectedActivityDetails: null,
        selectedActivityRecord: null,
        selectedTaskDetails: null,
        selectedTaskType: null,
        taskWorkspaceTab: 'overview',
        taskWorkspaceRelated: [],
        taskWorkspaceFiles: [],
        taskWorkspaceAudit: [],
        taskWorkspaceLoading: false,
        taskWorkspaceBusy: false,
        taskCopyCompanyId: '',
        taskCopyTargetStartDate: '',
        taskWorkspaceNow: Date.now(),
        meetingsSubTab: 'all',
        meetingSearch: '',
        meetingModeFilter: 'all',
        meetingStatusFilter: 'all',

        meetingFilters: {
            videoRecording: false,
            audioRecording: false,
            transcription: false,
            minutes: false
        },
        showMeetingModal: false,
        selectedMeetingDetails: null,
        meetingDetailsTab: 'information',
        selectedCoreActivityDetails: null,
        selectedCoreActivityType: null,
        coreActivityDetailTab: 'overview',
        meetingWorkspace: {},
        meetingWorkspaceLoading: false,
        meetingWorkspaceBusy: false,
        meetingMinutesText: '',
        meetingTranscriptText: '',
        meetingWorkspaceRelated: [],
        meetingWorkspaceAudit: [],
        newMeetingActionItem: { description: '', due_date: '', assigned_to_user_id: null },
        showVideoPlayer: false,
        showCallModal: false,

        // Calls list UI
        callViewTab: 'all',
        callSearch: '',
        callDirectionFilter: 'all',
        callStatusFilter: 'all',
        showEventModal: false,
        newNoteContent: '',
        editingNote: null,
        activeDownloadMenu: null,
        isUploading: false,
        isUploadingMinutes: false,
        isUploadingAudio: false,
        isUploadingTranscript: false,
        isCallAudioPlaying: false,
        callAudioCurrentTime: 0,
        callAudioDuration: 0,
        currentPage: 1,
        perPage: 50,
        perPageOptions: [10, 20, 30, 40, 50, 100],
        perPageOpen: false,

        // --- Media Recording State ---
        _videoRecorder: null,
        _audioRecorder: null,
        _videoStream: null,
        _audioStream: null,
        recordingVideoActive: false,
        recordingAudioActive: false,
        recordingError: '',
        recordedVideoUrl: null,
        recordedAudioUrl: null,
        _scheduledTimers: {},           // meetingId -> setTimeout ID
        activeRecordingMeetingId: null, // which meeting is currently recording
        // --- Notification prompt (user-gesture gate for getDisplayMedia) ---
        recordingPromptMeeting: null,   // meeting waiting for user to click Start
        // --- Transcription (Web Speech API) ---
        _speechRecognition: null,
        transcribingActive: false,
        liveTranscript: '',
        // --- Minutes ---
        meetingMinutes: '',
        // --- Elapsed timer ---
        recordingElapsed: 0,
        _elapsedTimer: null,

        // Decorative comment removed after text-encoding corruption.
        // capturedStream: a MediaStream from getDisplayMedia, provided by startRecordingForMeeting
        async startRecording(type, capturedStream) {
            try {
                let stream;
                if (type === 'video') {
                    // Full display stream: video + audio from the Google Meet tab
                    stream = capturedStream;
                } else {
                    // Audio-only: extract audio tracks from the display stream
                    const audioTracks = capturedStream.getAudioTracks();
                    stream = audioTracks.length
                        ? new MediaStream(audioTracks)
                        : capturedStream; // fallback: use full stream
                }
                const chunks = [];
                const mimeType = type === 'video'
                    ? (MediaRecorder.isTypeSupported('video/webm;codecs=vp9') ? 'video/webm;codecs=vp9' : 'video/webm')
                    : (MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : 'audio/webm');
                const recorder = new MediaRecorder(stream, { mimeType });
                recorder.ondataavailable = (e) => { if (e.data.size > 0) chunks.push(e.data); };
                recorder.onstop = () => {
                    const blob = new Blob(chunks, { type: mimeType });
                    const url = URL.createObjectURL(blob);
                    if (type === 'video') {
                        this.recordedVideoUrl = url;
                        this.recordingVideoActive = false;
                        const m = this.meetings.find(m => m.id === this.activeRecordingMeetingId);
                        if (m) m.video_url = url;
                    } else {
                        this.recordedAudioUrl = url;
                        this.recordingAudioActive = false;
                        const m = this.meetings.find(m => m.id === this.activeRecordingMeetingId);
                        if (m) m.audio_url = url;
                    }
                    stream.getTracks().forEach(t => t.stop());
                };
                recorder.start(1000);
                if (type === 'video') {
                    this._videoRecorder = recorder;
                    this._videoStream = stream;
                    this.recordingVideoActive = true;
                    this.recordedVideoUrl = null;
                } else {
                    this._audioRecorder = recorder;
                    this._audioStream = stream;
                    this.recordingAudioActive = true;
                    this.recordedAudioUrl = null;
                }
                this.recordingError = '';
            } catch (err) {
                this.recordingError = 'Recording error: ' + (err.message || err);
            }
        },

        stopRecording(type) {
            if (type === 'video' && this._videoRecorder && this._videoRecorder.state !== 'inactive') {
                this._videoRecorder.stop();
                this._videoRecorder = null;
            }
            if (type === 'audio' && this._audioRecorder && this._audioRecorder.state !== 'inactive') {
                this._audioRecorder.stop();
                this._audioRecorder = null;
            }
        },

        stopAllRecording() {
            this.stopRecording('video');
            this.stopRecording('audio');
            this.activeRecordingMeetingId = null;
            // Also stop elapsed timer
            if (this._elapsedTimer) { clearInterval(this._elapsedTimer); this._elapsedTimer = null; }
        },

        // Scheduler: at meeting time, show a notification prompt instead of calling getDisplayMedia directly.
        // calling getDisplayMedia directly (browsers block it from setTimeout)
        scheduleRecording(meeting) {
            if (!meeting || meeting.status !== 'upcoming') return;
            if (!meeting.has_video && !meeting.has_audio && !meeting.has_transcript) return;
            if (!meeting.date || !meeting.time) return;

            this.cancelScheduledRecording(meeting.id);

            const meetingStart = new Date(meeting.date + 'T' + meeting.time);
            if (isNaN(meetingStart.getTime())) return;

            const delay = meetingStart.getTime() - Date.now();

            const trigger = () => {
                delete this._scheduledTimers[meeting.id];
                const live = this.meetings.find(m => m.id === meeting.id);
                if (live && live.status === 'upcoming') {
                    this.promptRecordingForMeeting(live);
                }
            };

            if (delay <= 0) {
                trigger();
            } else {
                const timerId = setTimeout(trigger, Math.min(delay, 2147483647));
                this._scheduledTimers[meeting.id] = timerId;
            }
        },

        cancelScheduledRecording(meetingId) {
            if (this._scheduledTimers[meetingId] !== undefined) {
                clearTimeout(this._scheduledTimers[meetingId]);
                delete this._scheduledTimers[meetingId];
            }
        },

        // Show the notification prompt called by the scheduler.
        promptRecordingForMeeting(meeting) {
            this.recordingPromptMeeting = meeting;
        },

        dismissRecordingPrompt() {
            this.recordingPromptMeeting = null;
            this.recordingError = '';
        },

        // User gesture remains valid for getDisplayMedia.
        async confirmStartRecording() {
            const meeting = this.recordingPromptMeeting;
            if (!meeting) return;
            this.recordingPromptMeeting = null; // close the prompt immediately

            if (this.activeRecordingMeetingId === meeting.id) return;
            this.stopAllAndFinalize();
            this.activeRecordingMeetingId = meeting.id;
            this.liveTranscript = '';
            this.meetingMinutes = '';
            this.recordingElapsed = 0;

            const needsMedia = meeting.has_video || meeting.has_audio;
            const needsTranscript = meeting.has_transcript || meeting.has_minutes;

            try {
                if (needsMedia) {
                    // User selects the Google Meet tab in a valid user-gesture context.
                    const displayStream = await navigator.mediaDevices.getDisplayMedia({
                        video: true,
                        audio: true   // tab audio captures Google Meet voices
                    });
                    this.recordingError = '';

                    if (meeting.has_video) await this.startRecording('video', displayStream);
                    if (meeting.has_audio) await this.startRecording('audio', displayStream);

                    // Auto-stop recorders if user clicks browser's Stop Sharing button
                    displayStream.getVideoTracks().forEach(track => {
                        track.addEventListener('ended', () => this.stopAllAndFinalize(), { once: true });
                    });
                }

                if (needsTranscript) {
                    this.startTranscription(meeting);
                }

                // Start elapsed timer
                this._elapsedTimer = setInterval(() => { this.recordingElapsed++; }, 1000);

            } catch (err) {
                this.recordingError = 'Screen capture cancelled or denied: ' + (err.message || err);
                this.activeRecordingMeetingId = null;
            }
        },

        // Decorative comment removed after text-encoding corruption.
        startTranscription(meeting) {
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SR) {
                // Browser does not support Speech Recognition; skip silently.
                return;
            }
            const recognition = new SR();
            recognition.continuous = true;
            recognition.interimResults = false;
            recognition.lang = 'en-US';
            recognition.onresult = (event) => {
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) {
                        this.liveTranscript += event.results[i][0].transcript + ' ';
                    }
                }
            };
            recognition.onerror = () => { /* ignore errors, keep recording */ };
            recognition.onend = () => {
                if (this.transcribingActive) recognition.start(); // auto-restart
            };
            recognition.start();
            this._speechRecognition = recognition;
            this.transcribingActive = true;
        },

        stopTranscription() {
            if (this._speechRecognition) {
                this.transcribingActive = false; // prevent auto-restart
                this._speechRecognition.stop();
                this._speechRecognition = null;
            }
        },

        // Decorative comment removed after text-encoding corruption.
        generateMinutes(meeting) {
            if (!this.liveTranscript.trim()) return;
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            this.meetingMinutes =
                `MEETING MINUTES\n` +
                `================\n` +
                `Meeting: ${meeting.title}\n` +
                `Date: ${dateStr}\n` +
                `Time: ${meeting.time || timeStr}\n` +
                `Duration: ${Math.floor(this.recordingElapsed / 60)} min ${this.recordingElapsed % 60} sec\n` +
                `Attendees: ${meeting.attendees || 'N/A'}\n\n` +
                `TRANSCRIPT\n` +
                `----------\n` +
                this.liveTranscript.trim() + '\n\n' +
                `[Generated automatically at ${timeStr}]`;
            // Attach to the meeting object for display in the details panel
            const live = this.meetings.find(m => m.id === meeting.id);
            if (live) {
                live.transcript_text = this.liveTranscript.trim();
                live.minutes_text = this.meetingMinutes;
            }
        },

        // Decorative comment removed after text-encoding corruption.
        stopAllAndFinalize() {
            const meetingId = this.activeRecordingMeetingId;
            const meeting = meetingId ? this.meetings.find(m => m.id === meetingId) : null;
            this.stopTranscription();
            this.stopAllRecording();
            if (meeting) this.generateMinutes(meeting);
            this.recordingElapsed = 0;
        },

        async startRecordingForMeeting(meeting) {
            // Legacy method now delegates to the recording prompt approach.
            this.promptRecordingForMeeting(meeting);
        },

        async uploadVideo(event, meetingId) {
            const file = event.target.files[0];
            if (!file) return;

            this.isUploading = true;
            try {
                const chunkSize = 1024 * 1024; // 1MB chunks
                const totalChunks = Math.ceil(file.size / chunkSize);
                let updatedMeeting = null;
                const csrfToken = document.querySelector('meta[name=csrf-token]').getAttribute('content');

                for (let i = 0; i < totalChunks; i++) {
                    const chunk = file.slice(i * chunkSize, (i + 1) * chunkSize);
                    const formData = new FormData();
                    formData.append('video', chunk);
                    formData.append('chunkIndex', i);
                    formData.append('totalChunks', totalChunks);
                    formData.append('fileName', file.name);

                    const response = await fetch(`/api/meetings/${meetingId}/upload-video`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: formData
                    });

                    if (!response.ok) {
                        const errData = await response.json().catch(() => ({}));
                        throw new Error(errData.message || errData.error || 'Server returned ' + response.status);
                    }

                    if (i === totalChunks - 1) {
                        updatedMeeting = await response.json();
                    }
                }

                if (updatedMeeting) {
                    // Update the meetings array
                    const index = this.meetings.findIndex(m => m.id === meetingId);
                    if (index !== -1) {
                        this.meetings[index] = updatedMeeting;
                    }
                    if (this.selectedMeetingDetails && this.selectedMeetingDetails.id === meetingId) {
                        this.selectedMeetingDetails = updatedMeeting;
                    }
                }
            } catch (error) {
                console.error('Error uploading video:', error);
                alert('Upload failed: ' + error.message);
            } finally {
                this.isUploading = false;
                event.target.value = ''; // Reset input
            }
        },

        async uploadMinutes(meetingId, event) {
            const file = event.target.files[0];
            if (!file) return;

            this.isUploadingMinutes = true;
            const formData = new FormData();
            formData.append('minutes', file);

            try {
                const response = await fetch(`/api/meetings/${meetingId}/upload-minutes`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: formData
                });
                if (response.ok) {
                    const updatedMeeting = await response.json();
                    const index = this.meetings.findIndex(m => m.id === meetingId);
                    if (index !== -1) {
                        this.meetings[index] = updatedMeeting;
                    }
                    if (this.selectedMeetingDetails && this.selectedMeetingDetails.id === meetingId) {
                        this.selectedMeetingDetails = updatedMeeting;
                    }
                }
            } catch (error) {
                console.error('Error uploading minutes:', error);
                alert('Upload failed: ' + error.message);
            } finally {
                this.isUploadingMinutes = false;
                event.target.value = '';
            }
        },

        formatTimeGeneral(seconds) {
            if (!seconds || isNaN(seconds)) return '00:00';
            let m = Math.floor(seconds / 60);
            let s = Math.floor(seconds % 60);
            return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        },

        toggleCallAudio() {
            let aud = this.$refs.callAudioPlayer;
            if (!aud) return;
            if (aud.paused) {
                aud.play();
                this.isCallAudioPlaying = true;
            } else {
                aud.pause();
                this.isCallAudioPlaying = false;
            }
        },

        updateCallAudioTime() {
            let aud = this.$refs.callAudioPlayer;
            if (!aud) return;
            this.callAudioCurrentTime = aud.currentTime;
            if (aud.duration && !isNaN(aud.duration)) {
                this.callAudioDuration = aud.duration;
            }
        },

        seekCallAudio(event) {
            let aud = this.$refs.callAudioPlayer;
            if (!aud) return;
            let rect = event.currentTarget.getBoundingClientRect();
            let pos = (event.clientX - rect.left) / rect.width;
            aud.currentTime = pos * this.callAudioDuration;
        },

        async deleteCallAudio(callId) {
            if (!callId) return;
            if (!confirm('Are you sure you want to remove this audio recording?')) return;

            try {
                const response = await fetch(`/api/calls/${callId}/audio`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    }
                });

                if (response.ok) {
                    const updatedCall = await response.json();
                    const index = this.calls.findIndex(c => c.id === callId);
                    if (index !== -1) {
                        this.calls[index] = updatedCall;
                    }
                    if (this.selectedTaskDetails && this.selectedTaskDetails.id === callId) {
                        this.selectedTaskDetails = updatedCall;
                        this.isCallAudioPlaying = false;
                        this.callAudioCurrentTime = 0;
                        if (this.$refs.callAudioPlayer) {
                            this.$refs.callAudioPlayer.pause();
                            this.$refs.callAudioPlayer.load();
                        }
                    }
                }
            } catch (error) {
                console.error('Error removing call audio:', error);
            }
        },

        async uploadCallAudio(event, callId) {
            if (!callId) return;
            const file = event.target.files[0];
            if (!file) return;

            this.isUploadingAudio = true;
            try {
                const chunkSize = 1024 * 1024; // 1MB chunks
                const totalChunks = Math.ceil(file.size / chunkSize);
                let updatedCall = null;
                const csrfToken = document.querySelector('meta[name=csrf-token]').getAttribute('content');

                for (let i = 0; i < totalChunks; i++) {
                    const chunk = file.slice(i * chunkSize, (i + 1) * chunkSize);
                    const formData = new FormData();
                    formData.append('audio', chunk);
                    formData.append('chunkIndex', i);
                    formData.append('totalChunks', totalChunks);
                    formData.append('fileName', file.name);

                    const response = await fetch(`/api/calls/${callId}/upload-audio`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: formData
                    });

                    if (!response.ok) {
                        const errData = await response.json().catch(() => ({}));
                        throw new Error(errData.message || errData.error || 'Server returned ' + response.status);
                    }

                    if (i === totalChunks - 1) {
                        updatedCall = await response.json();
                    }
                }

                if (updatedCall) {
                    const index = this.calls.findIndex(c => c.id === callId);
                    if (index !== -1) {
                        this.calls[index] = updatedCall;
                    }

                    if (this.selectedTaskDetails && this.selectedTaskDetails.id === callId) {
                        this.selectedTaskDetails = updatedCall;
                    }
                }
            } catch (error) {
                alert('Upload failed: ' + error.message);
                console.error('Error uploading call audio:', error);
            } finally {
                this.isUploadingAudio = false;
                event.target.value = '';
            }
        },

        init() {
            this.$watch('activeTab', () => this.currentPage = 1);
            this.$watch('hasActivityDetail', visible => { if (!visible) this.restoreActivityListPosition(); });
            this.expandActiveSidebarGroup();
            this.$watch('activitySidebarGroup', () => this.expandActiveSidebarGroup());
            this.$watch('filterSelection.task', () => this.currentPage = 1);
            this.$watch('filterSelection.events', () => this.currentPage = 1);
            this.$watch('filterSelection.call', () => this.currentPage = 1);
            this.$watch('meetingsSubTab', () => this.currentPage = 1);
            this.$watch('meetingSearch', () => this.currentPage = 1);
            this.$watch('meetingModeFilter', () => this.currentPage = 1);
            this.$watch('meetingStatusFilter', () => this.currentPage = 1);
            this.$watch('allActivitiesSearch', () => this.currentPage = 1);
            [
                'allActivitiesAccountFilter',
                'allActivitiesStatusFilter',
                'allActivitiesDateFilter',
                'allActivitiesMonthFilter',
                'allActivitiesDateFrom',
                'allActivitiesDateTo',
                'allActivitiesTypeFilters',
                'allActivitiesDurationFilter'
            ].forEach(filter => this.$watch(filter, () => {
                this.currentPage = 1;
                this.fetchAllActivitiesReport();
            }));
            this.$watch('perPage', () => this.currentPage = 1);
            this.$watch('visitorLogTab', (tab) => {
                if (tab === 'visit-records') this.fetchVisitRecords();
            });

            // Initial fetch (scheduleRecording is called inside fetchActivities)
            this.fetchActivities().then(async () => {
                if (await this.openActivityFromLink()) return;
                if (localStorage.getItem('activity_company_prefill')) {
                    this.applyCompanyPrefillFromShortcut();
                } else {
                    this.applyContactPrefillFromShortcut();
                }
            });
            this.fetchVisitRecords();

            // Refresh every 60 seconds and reschedule newly added meetings.
            setInterval(() => {
                this.fetchActivities();
            }, 60000);
            setInterval(() => {
                if (this.showMessageDetails && this.selectedMessageDetails?.channel === 'Messenger' && !this.messageSaving) {
                    this.refreshMessageConversation().catch(() => {});
                }
            }, 10000);

            setInterval(() => {
                this.taskWorkspaceNow = Date.now();
            }, 1000);
        },

        async visitorApiRequest(url, options = {}) {
            const headers = { Accept: 'application/json', ...(options.headers || {}) };

            if (options.body) {
                headers['Content-Type'] = 'application/json';
                headers['X-CSRF-TOKEN'] = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
            }

            const response = await fetch(url, { ...options, headers });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationErrors = payload.errors
                    ? Object.values(payload.errors).flat().filter(Boolean).join(' ')
                    : '';
                throw new Error(validationErrors || payload.message || `Visitor Log request failed (${response.status}).`);
            }

            return payload;
        },

        async fetchVisitors() {
            const search = this.visitorSearch.trim();
            this.selectedVisitor = null;
            this.visitorSearchResults = [];
            this.visitorLogError = '';

            if (!search) return;

            this.visitorSearchLoading = true;
            try {
                const payload = await this.visitorApiRequest(`/api/visitors?search=${encodeURIComponent(search)}`);
                this.visitorSearchResults = Array.isArray(payload.visitors) ? payload.visitors : [];
            } catch (error) {
                this.visitorLogError = error.message || 'Unable to search visitors.';
            } finally {
                this.visitorSearchLoading = false;
            }
        },

        selectVisitor(visitor) {
            this.selectedVisitor = visitor;
            this.visitorSearch = `${visitor.name} (${visitor.visitor_reference})`;
            this.visitorSearchResults = [];
            this.visitorLogError = '';
        },

        async createFirstTimeVisitor() {
            this.firstTimeVisitorSaving = true;
            this.visitorLogError = '';

            try {
                const payload = await this.visitorApiRequest('/api/visitors', {
                    method: 'POST',
                    body: JSON.stringify({
                        name: this.newVisitor.name,
                        mobile: this.newVisitor.mobile || null,
                        email: this.newVisitor.email || null,
                        company_name: this.newVisitor.company_name || null
                    })
                });
                this.selectVisitor(payload);
                this.newVisitor = { name: '', mobile: '', email: '', company_name: '' };
                this.firstTimeVisitorOpen = false;
            } catch (error) {
                this.visitorLogError = error.message || 'Unable to create the visitor.';
            } finally {
                this.firstTimeVisitorSaving = false;
            }
        },

        captureVisitorLocation() {
            return new Promise((resolve) => {
                if (!navigator.geolocation) {
                    resolve({});
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    ({ coords, timestamp }) => resolve({
                        latitude: coords.latitude,
                        longitude: coords.longitude,
                        accuracy_metres: coords.accuracy,
                        captured_at: new Date(timestamp).toISOString(),
                        source: 'browser'
                    }),
                    () => resolve({}),
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 }
                );
            });
        },

        async checkInVisitor() {
            if (!this.selectedVisitor) {
                this.visitorLogError = 'Select a returning visitor or create a first-time visitor before checking in.';
                return;
            }

            this.visitorLogLoading = true;
            this.visitorLogError = '';

            try {
                const location = await this.captureVisitorLocation();
                const request = { visitor_id: this.selectedVisitor.id, ...location };
                if (this.visitorBranchId) request.branch_id = Number(this.visitorBranchId);
                if (this.visitorActivityType && this.visitorActivityId) {
                    request.activity_type = this.visitorActivityType;
                    request.activity_id = Number(this.visitorActivityId);
                }

                await this.visitorApiRequest('/api/visits/check-in', {
                    method: 'POST',
                    body: JSON.stringify(request)
                });
                this.selectedVisitor = null;
                this.visitorSearch = '';
                this.visitorBranchId = '';
                this.visitorActivityType = '';
                this.visitorActivityId = '';
                await this.fetchVisitRecords();
            } catch (error) {
                this.visitorLogError = error.message || 'Unable to check in the visitor.';
            } finally {
                this.visitorLogLoading = false;
            }
        },

        async checkOutVisitor(visit) {
            this.visitorLogLoading = true;
            this.visitorLogError = '';

            try {
                const location = await this.captureVisitorLocation();
                const request = { ...location };
                if (this.visitorBranchId) request.branch_id = Number(this.visitorBranchId);
                await this.visitorApiRequest(`/api/visits/${visit.id}/check-out`, {
                    method: 'POST',
                    body: JSON.stringify(request)
                });
                await this.fetchVisitRecords();
            } catch (error) {
                this.visitorLogError = error.message || 'Unable to check out the visitor.';
            } finally {
                this.visitorLogLoading = false;
            }
        },

        async fetchVisitRecords() {
            this.visitorLogError = '';
            try {
                const params = new URLSearchParams();
                if (this.visitRecordSearch.trim()) params.set('search', this.visitRecordSearch.trim());
                if (this.visitRecordStatus) params.set('status', this.visitRecordStatus);
                const suffix = params.toString() ? `?${params.toString()}` : '';
                const [payload, currentPayload] = await Promise.all([
                    this.visitorApiRequest(`/api/visits${suffix}`),
                    this.visitorApiRequest('/api/visits?status=Checked%20In')
                ]);
                this.visitRecords = Array.isArray(payload.visits) ? payload.visits : [];
                this.currentVisits = Array.isArray(currentPayload.current_visits) ? currentPayload.current_visits : [];
            } catch (error) {
                this.visitRecords = [];
                this.currentVisits = [];
                this.visitorLogError = error.message || 'Unable to load Visitor Log records.';
            }
        },

        async viewVisitDetails(visit) {
            const detailRequest = this.beginActivityDetail();
            this.selectedVisitDetails = visit;
            this.visitDetailsOpen = true;
            this.visitorLogError = '';
            try {
                const details = await this.visitorApiRequest(`/api/visits/${visit.id}`);
                if (detailRequest === this.activityDetailRequest) this.selectedVisitDetails = details;
            } catch (error) {
                this.visitorLogError = error.message || 'Unable to load visit details.';
            }
        },

        setVisitRecordStatus(status) {
            this.visitRecordStatus = status;
            this.visitRecordFilterOpen = false;
            this.fetchVisitRecords();
        },

        formatVisitorDate(value) {
            if (!value) return 'Not checked out';
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? value : date.toLocaleString([], {
                year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
            });
        },

        visitorMovementSummary(movement) {
            if (!movement) return 'Location not recorded';
            return movement.branch?.name || movement.location_label || (movement.latitude !== null ? 'GPS captured' : 'Location not recorded');
        },

        get filteredCurrentVisits() {
            const search = this.currentVisitorSearch.trim().toLowerCase();
            if (!search) return this.currentVisits;
            return this.currentVisits.filter(visit => [
                visit.visit_reference,
                visit.visitor?.visitor_reference,
                visit.visitor?.name,
                visit.company
            ].filter(Boolean).join(' ').toLowerCase().includes(search));
        },

        get visitorActivityOptions() {
            const collections = {
                Task: this.tasks,
                Event: this.events,
                Call: this.calls,
                Meeting: this.meetings
            };
            const records = collections[this.visitorActivityType] || [];
            return records.map(record => ({
                id: record.id,
                label: record.name || record.title || record.contact || record.purpose || `#${record.id}`
            }));
        },

        async fetchActivities() {
            const messageRequest = this.fetchMessages();
            const officialBusinessRequest = this.fetchOfficialBusinessRecords();

            try {
                const response = await fetch('/api/activities');
                const data = await response.json();
                this.tasks = data.tasks;
                this.events = data.events;
                this.calls = data.calls;
                this.meetings = data.meetings;
                this.emails = Array.isArray(data.emails) ? data.emails : [];
                this.emailMailboxes = Array.isArray(data.emailMailboxes) ? data.emailMailboxes : [];
                this.unifiedActivities = Array.isArray(data.activities) ? data.activities : [];
                const activityKeys = new Set(this.unifiedActivities.map(activity => this.activityKey(activity)));
                this.selectedActivities = this.selectedActivities.filter(key => activityKeys.has(key));
                this.relatedOptions = data.relatedOptions || [];
                this.relatedRecords = data.relatedRecords || [];
                this.contacts = data.contacts || [];
                this.messageUserRecords = data.taskUsers || [];
                this.teamUsers = Array.isArray(data.teamUsers) ? data.teamUsers : [];
                this.currentUserId = data.currentUserId || null;
                if (data.users) {
                    this.systemUsers = data.users;
                    this.currentUser = data.currentUser || '';
                    const defaultUser = this.currentUser || this.systemUsers[0] || '';
                    if (!this.newTask.owner) this.newTask.owner = defaultUser;
                    if (!this.newEvent.host) this.newEvent.host = defaultUser;
                    if (!this.newCall.owner) this.newCall.owner = defaultUser;
                    // For new calls, default to Outbound with user as 'From'
                    if (this.newCall && !this.newCall.id && !this.newCall.from && !this.newCall.to) {
                        this.newCall.from = defaultUser;
                        this.newCall.type = 'Outbound';
                    }
                    if (!this.newMeeting.owner) this.newMeeting.owner = defaultUser;
                }
                // Schedule recordings for all upcoming meetings that need it
                if (this.meetings) {
                    this.meetings.forEach(m => {
                        // Only add a timer if none exists yet for this meeting
                        if ((m.has_video || m.has_audio) && this._scheduledTimers[m.id] === undefined
                            && this.activeRecordingMeetingId !== m.id) {
                            this.scheduleRecording(m);
                        }
                    });
                }
                await messageRequest;
                await officialBusinessRequest;
                await this.fetchAllActivitiesReport();
            } catch (error) {
                console.error('Error fetching activities:', error);
            }
        },

        async fetchOfficialBusinessRecords() {
            this.officialBusinessLoading = true;
            this.officialBusinessRequestError = '';

            try {
                const response = await fetch('/api/official-business', {
                    headers: { Accept: 'application/json' }
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const statusMessages = {
                        401: 'Your session has expired. Please sign in again.',
                        403: 'You do not have permission to access Official Business records.',
                        404: 'The Official Business endpoint was not found.',
                        422: 'The Official Business request could not be validated.'
                    };
                    throw new Error(statusMessages[response.status] || payload.message || 'Unable to load Official Business records.');
                }

                this.officialBusinessRecords = Array.isArray(payload.records) ? payload.records : [];

                this.travelDeskRecords = this.officialBusinessRecords
                    .map(record => {
                        const status = String(record.status || '').toLowerCase();
                        const expectedReturn = record.expectedReturn
                            ? new Date(record.expectedReturn)
                            : null;

                        let movement = null;

                        const workflowVersion = Number(
                            record.workflowVersion
                            || record.workflow_version
                            || 1
                        );

                        if (status === 'approved') {
                            movement = workflowVersion >= 3
                                ? 'Awaiting Readiness'
                                : 'Ready';
                        } else if (status === 'ready for travel') {
                            movement = 'Ready';
                        } else if (['in progress', 'ongoing'].includes(status)) {
                            movement = expectedReturn
                                && !Number.isNaN(expectedReturn.getTime())
                                && expectedReturn < new Date()
                                ? 'Overdue'
                                : 'Ongoing';
                        } else if (status === 'returned') {
                            movement = 'Returned';
                        }

                        if (!movement) return null;

                        return {
                            ...record,
                            ref: record.ref || record.ob_reference_no || ('OBF-' + record.id),
                            traveler: record.employee || record.employee_name || 'Not recorded',
                            client: record.clients || record.client_name || 'Internal / JK&C',
                            destination: record.destination || record.destination_primary || 'Not recorded',
                            movement,
                        };
                    })
                    .filter(Boolean);
                this.officialBusinessCapabilities = payload.capabilities || {};
                this.officialBusinessCanManage = Boolean(payload.can_manage);
                this.officialBusinessWorkspaceUrl = payload.workspace_url || this.officialBusinessWorkspaceUrl;

                if (
                    this.officialBusinessCanManage
                    && this.officialBusinessCapabilities.soft_delete
                ) {
                    await this.fetchDeletedOfficialBusinessRecords();
                } else {
                    this.deletedOfficialBusinessRecords = [];
                }
            } catch (error) {
                this.officialBusinessRecords = [];
                this.officialBusinessCanManage = false;
                this.officialBusinessRequestError = error.message || 'Unable to load Official Business records.';
                console.error('Error fetching Official Business records:', error);
            } finally {
                this.officialBusinessLoading = false;
            }
        },

        async messageApiRequest(url, options = {}) {
            const headers = { Accept: 'application/json', ...(options.headers || {}) };

            if (options.body) {
                headers['Content-Type'] = 'application/json';
                headers['X-CSRF-TOKEN'] = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
            }

            const response = await fetch(url, { ...options, headers });
            const payload = response.status === 204
                ? null
                : await response.json().catch(() => ({}));

            if (!response.ok) {
                const statusMessages = {
                    401: 'Your session has expired. Please sign in again.',
                    403: 'You do not have permission to access Messages.',
                    404: 'The requested conversation was not found.',
                    422: 'Please correct the message fields and try again.'
                };
                throw new Error(this.apiErrorMessage(payload, statusMessages[response.status] || 'The message request failed.'));
            }

            return payload;
        },

        async fetchMessages() {
            this.messageLoading = true;
            this.messageRequestError = '';

            try {
                this.messages = await this.messageApiRequest('/api/message-conversations');
            } catch (error) {
                this.messageRequestError = error.message || 'Unable to load conversations.';
                console.error('Error fetching message conversations:', error);
            } finally {
                this.messageLoading = false;
            }
        },

        selectRelationshipContact(form) {
            const contact = this.contacts.find(item => String(item.id) === String(form.contactId));
            const ids = contact?.company_ids || [];
            if (!form.companyId && ids.length === 1) form.companyId = String(ids[0]);
        },
        relationshipNeedsCompany(form) {
            const contact = this.contacts.find(item => String(item.id) === String(form.contactId));
            return !form.companyId && (contact?.company_ids || []).length > 1;
        },
        relatedAccountLabel(activity) {
            return [activity.account, activity.contact].filter(Boolean).join(' - ') || '-';
        },
        async saveMessageRelationships() {
            if (!this.selectedMessageDetails?.id) return;
            try {
                await this.messageApiRequest('/api/message-conversations/' + this.selectedMessageDetails.id, {
                    method: 'PATCH', body: JSON.stringify({company_id: Number(this.messageRelationships.companyId) || null, contact_id: Number(this.messageRelationships.contactId) || null})
                });
                await this.fetchActivities();
                await this.refreshMessageConversation();
            } catch (error) { alert(error.message); }
        },
        normalizedMessageValue(value) {
            return String(value || '').trim().toLowerCase();
        },

        resolveMessageCompanyId(label) {
            if (!label) return null;
            const normalized = this.normalizedMessageValue(label);
            return this.messageCompanyRecords.find(record => this.normalizedMessageValue(record.label) === normalized)?.id || null;
        },

        resolveMessageContactId(label) {
            if (!label) return null;
            const normalized = this.normalizedMessageValue(label);
            return this.contacts.find(contact => [contact.name, contact.label, contact.email, contact.phone]
                .some(value => this.normalizedMessageValue(value) === normalized))?.id || null;
        },

        resolveMessageUserId(label) {
            if (!label) return null;
            const normalized = this.normalizedMessageValue(label);
            return this.messageUserRecords.find(user => [user.name, user.email]
                .some(value => this.normalizedMessageValue(value) === normalized))?.id || null;
        },

        messagePayload(action) {
            const companyId = Number(this.newMessage.companyId) || null;
            const contactId = Number(this.newMessage.contactId) || null;
            const assignedUserId = this.resolveMessageUserId(this.newMessage.assignedTo);


            if (this.newMessage.assignedTo && !assignedUserId) throw new Error('Select a known employee.');
            if (!this.newMessage.channel) throw new Error('Select a Channel.');
            if (!this.newMessage.body?.trim()) throw new Error('Enter a Message.');

            return {
                company_id: companyId,
                contact_id: contactId,
                assigned_user_id: assignedUserId,
                conversation_title: this.newMessage.conversationTitle || null,
                conversation_summary: this.newMessage.conversationSummary || null,
                channel: this.newMessage.channel,
                direction: this.newMessage.direction || 'Conversation Thread',
                duration_minutes: this.newMessage.durationMinutes === '' ? null : Number(this.newMessage.durationMinutes),
                line_account: this.newMessage.lineAccount || null,
                recipient: this.newMessage.recipient || null,
                body: this.newMessage.body,
                handling: this.newMessage.handling || 'Open',
                technical_metadata: {
                    frontend_action: action,
                    external_delivery_attempted: false
                }
            };
        },

        resetNewMessage() {
            this.newMessage = { companyId: '', contactId: '',
                conversationTitle: '', conversationSummary: '', client: '', contact: '', channel: 'SMS', lineAccount: '',
                recipient: '', body: '', direction: 'Conversation Thread', durationMinutes: '', handling: 'Open', assignedTo: ''
            };
        },

        async submitNewMessage(action) {
            if (this.messageSaving) return;
            this.messageRequestError = '';
            this.messageSaving = true;

            try {
                const conversation = await this.messageApiRequest('/api/message-conversations', {
                    method: 'POST',
                    body: JSON.stringify(this.messagePayload(action))
                });
                this.messages = [conversation, ...this.messages.filter(item => item.id !== conversation.id)];
                this.resetNewMessage();
                this.showNewMessage = false;
                await this.fetchActivities();
                alert(action === 'draft'
                    ? 'Draft stored as a pending message. No external delivery was attempted.'
                    : 'Message stored as pending. No external delivery was attempted.');
            } catch (error) {
                this.messageRequestError = error.message || 'Unable to save the message.';
            } finally {
                this.messageSaving = false;
            }
        },

        async openMessageConversation(conversation) {
            const detailRequest = this.beginActivityDetail();
            this.messageRequestError = '';
            this.selectedMessageDetails = conversation; this.messageRelationships = {companyId: String(conversation.company_id || ''), contactId: String(conversation.contact_id || '')};
            this.showMessageDetails = true;
            this.messageAttachments = [];
            this.messageDraftAttachmentIds = [];
            await this.loadMessageAttachments(conversation.id);

            try {
                const details = await this.messageApiRequest('/api/message-conversations/' + conversation.id);
                if (detailRequest === this.activityDetailRequest) this.selectedMessageDetails = details;
            } catch (error) {
                if (detailRequest === this.activityDetailRequest) this.messageRequestError = error.message || 'Unable to load conversation details.';
            }
        },

        resetMessageDraft() {
            ++this.messageDraftSession;
            this.messageSendRequest = null;
            ++this.messageAttachmentLoadRequest;
            this.messageDraftAttachmentIds = [];
            this.messageAttachments = [];
            this.messageAttachmentLoading = false;
            this.messageAttachmentUploading = false;
            this.newMessageEntry.body = '';
        },
        isCurrentMessageDraft(session, conversationId) {
            return session === this.messageDraftSession && this.showMessageDetails &&
                Number(this.selectedMessageDetails?.id) === Number(conversationId);
        },
        async refreshMessageConversation() {
            const session = this.messageDraftSession;
            const id = this.selectedMessageDetails?.id;
            if (!id) return;
            const details = await this.messageApiRequest('/api/message-conversations/' + id);
            if (this.isCurrentMessageDraft(session, id)) this.selectedMessageDetails = details;
        },
        async loadMessageAttachments(conversationId = null) {
            const id = Number(conversationId || this.selectedMessageDetails?.id);
            const session = this.messageDraftSession;
            const request = ++this.messageAttachmentLoadRequest;

            if (!id) {
                this.messageAttachments = [];
                return;
            }

            this.messageAttachmentLoading = true;

            try {
                const response = await fetch(
                    `/api/activities/MESSAGE/${id}/attachments`,
                    {
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        payload.message ||
                        'Unable to load message attachments.'
                    );
                }

                if (!this.isCurrentMessageDraft(session, id) || request !== this.messageAttachmentLoadRequest) return;
                const attachments = Array.isArray(payload.attachments) ? payload.attachments : [];
                // Keep confirmed uploads visible even if a concurrent list read predates them.
                const ids = new Set(attachments.map(attachment => Number(attachment.id)));
                this.messageAttachments = [...attachments, ...this.messageAttachments.filter(
                    attachment => this.isMessageDraftAttachment(attachment) && !ids.has(Number(attachment.id))
                )];
            } catch (error) {
                console.error('Unable to load message attachments:', error);
                if (this.isCurrentMessageDraft(session, id) && request === this.messageAttachmentLoadRequest) {
                    this.messageRequestError = 'Unable to refresh conversation files. Current reply attachments are preserved.';
                }
            } finally {
                if (this.isCurrentMessageDraft(session, id) && request === this.messageAttachmentLoadRequest) this.messageAttachmentLoading = false;
            }
        },

        async uploadMessageAttachment(event) {
            const input = event?.target;
            const file = input?.files?.[0];
            const conversationId = Number(this.selectedMessageDetails?.id);
            const session = this.messageDraftSession;

            if (!file || !conversationId || this.messageAttachmentUploading || this.messageSaving) return;

            if (file.size > 20 * 1024 * 1024) {
                alert('Attachment must not exceed 20 MB.');
                input.value = '';
                return;
            }

            this.messageAttachmentUploading = true;
            this.messageRequestError = '';

            const formData = new FormData();
            formData.append('attachment', file);
            formData.append(
                'category',
                this.messageAttachmentCategory(file)
            );

            try {
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content');

                const response = await fetch(
                    `/api/activities/MESSAGE/${conversationId}/attachments`,
                    {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            ...(csrfToken
                                ? { 'X-CSRF-TOKEN': csrfToken }
                                : {})
                        },
                        body: formData
                    }
                );

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const validationMessage = payload.errors
                        ? Object.values(payload.errors).flat()[0]
                        : null;

                    throw new Error(
                        validationMessage ||
                        payload.message ||
                        'Attachment upload failed.'
                    );
                }

                const attachmentId = Number(payload?.id);

                if (!attachmentId) {
                    throw new Error(
                        'Attachment uploaded, but its attachment ID was not returned.'
                    );
                }

                if (!this.isCurrentMessageDraft(session, conversationId)) return;
                ++this.messageAttachmentLoadRequest;
                this.messageAttachments = [...this.messageAttachments.filter(attachment => Number(attachment.id) !== attachmentId), payload];
                this.selectMessageDraftAttachment(attachmentId);
                await this.loadMessageAttachments(conversationId);

            } catch (error) {
                if (this.isCurrentMessageDraft(session, conversationId)) this.messageRequestError =
                    error.message ||
                    'Unable to upload the attachment.';
            } finally {
                if (this.isCurrentMessageDraft(session, conversationId)) this.messageAttachmentUploading = false;

                if (input) {
                    input.value = '';
                }
            }
        },

        messageAttachmentCategory(file) {
            const type = String(file?.type || '').toLowerCase();

            if (type.startsWith('image/')) return 'image';
            if (type.startsWith('audio/')) return 'audio';
            if (type.startsWith('video/')) return 'video';

            return 'document';
        },

        messageAttachmentName(attachment) {
            return (
                attachment?.original_filename ||
                attachment?.filename ||
                'Attachment'
            );
        },

        messageAttachmentSize(bytes) {
            const size = Number(bytes || 0);

            if (!size) return '';

            if (size < 1024) {
                return `${size} B`;
            }

            if (size < 1024 * 1024) {
                return `${(size / 1024).toFixed(1)} KB`;
            }

            return `${(size / (1024 * 1024)).toFixed(1)} MB`;
        },
        isMessageDraftAttachment(attachment) {
            const id = Number(attachment?.id);

            return id > 0 &&
                this.messageDraftAttachmentIds.includes(id);
        },

        selectMessageDraftAttachment(attachmentId) {
            const id = Number(attachmentId);
            if (this.messageSaving || !this.messageAttachments.some(attachment => Number(attachment.id) === id)) return;
            this.messageDraftAttachmentIds = [...new Set([...this.messageDraftAttachmentIds.map(Number), id])];
        },
        removeMessageDraftAttachment(attachmentId) {
            if (this.messageSaving) return;
            const id = Number(attachmentId);

            this.messageDraftAttachmentIds =
                this.messageDraftAttachmentIds.filter(
                    draftId => Number(draftId) !== id
                );
        },
        messageDraftAttachments() {
            const ids = new Set(this.messageDraftAttachmentIds.map(Number));

            return this.messageAttachments.filter(
                attachment => ids.has(Number(attachment?.id))
            );
        },

        messageDraftHasSingleMessengerImage() {
            const attachments = this.messageDraftAttachments();

            if (attachments.length !== 1) return false;

            const mime = String(
                attachments[0]?.mime_type || ''
            ).toLowerCase();

            return mime.startsWith('image/');
        },

        messageDraftCanSendExternally() {
            return this.messageDraftAttachmentIds.length === 0 ||
                this.messageDraftHasSingleMessengerImage();
        },

        async addMessageEntry() {
            if (this.messageSaving || this.messageAttachmentUploading) return;
            const body = this.newMessageEntry.body?.trim();
            if (!body || !this.selectedMessageDetails?.id) {
                this.messageRequestError = 'Enter a Message.';
                return;
            }

            this.messageRequestError = '';
            this.messageSaving = true;
            const session = this.messageDraftSession;
            const conversationId = this.selectedMessageDetails.id;

            try {
                const sendExternal =
                    !!this.selectedMessageDetails.messenger_outbound_available &&
                    this.messageDraftCanSendExternally();

                const attachmentKey = [...this.messageDraftAttachmentIds]
                    .map(Number)
                    .sort((a, b) => a - b)
                    .join(',');

                if (
                    sendExternal &&
                    (
                        !this.messageSendRequest ||
                        this.messageSendRequest.body !== body ||
                        this.messageSendRequest.conversationId !== conversationId ||
                        this.messageSendRequest.attachmentKey !== attachmentKey
                    )
                ) {
                    // getRandomValues also works on local HTTP Herd origins without randomUUID.
                    const bytes = crypto.getRandomValues(new Uint8Array(16));
                    bytes[6] = (bytes[6] & 15) | 64;
                    bytes[8] = (bytes[8] & 63) | 128;
                    const hex = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
                    const id = [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
                    this.messageSendRequest = {
                        id,
                        body,
                        conversationId,
                        attachmentKey
                    };
                }
                await this.messageApiRequest('/api/message-conversations/' + conversationId + '/messages', {
                    method: 'POST',
                    body: JSON.stringify({
                        body,
                        send_external: sendExternal,
                        client_request_id: sendExternal ? this.messageSendRequest.id : null,
                        direction: 'Outgoing',
                        delivery_status: 'Pending',
                        attachment_ids: [...this.messageDraftAttachmentIds],
                        technical_metadata: { frontend_action: 'send', external_delivery_attempted: false }
                    })
                });
                if (this.isCurrentMessageDraft(session, conversationId)) {
                    this.messageSendRequest = null;
                    this.newMessageEntry.body = '';
                    this.messageDraftAttachmentIds = [];
                    await this.refreshMessageConversation();
                }
                await this.fetchActivities();
                alert(sendExternal ? 'Reply queued for Messenger. Check its delivery status before submitting another reply.' : 'Message stored as pending. No external delivery was attempted.');
            } catch (error) {
                if (this.isCurrentMessageDraft(session, conversationId)) this.messageRequestError = error.message || 'Unable to add the message.';
            } finally {
                this.messageSaving = false;
            }
        },

        messageContactName(contact) {
            if (!contact) return '';
            return [
                contact.first_name,
                contact.middle_name,
                contact.last_name,
                contact.name_extension
            ].filter(Boolean).join(' ');
        },

        messageTechnicalSummary(conversation) {
            if (conversation?.external_conversation_id) return conversation.external_conversation_id;
            if (!conversation?.technical_metadata) return '-';

            return Object.entries(conversation.technical_metadata)
                .map(([key, value]) => key + ': ' + (typeof value === 'object' ? JSON.stringify(value) : String(value)))
                .join(', ');
        },

        messageConversationSummary(conversation) {
            if (!conversation) return '';

            return [
                conversation.company?.company_name,
                this.messageContactName(conversation.contact),
                conversation.channel,
                conversation.line_account,
                conversation.handling,
                conversation.assigned_user?.name || conversation.assigned_user?.email
            ].filter(Boolean).join(' - ');
        },

        formatMessageDate(value) {
            if (!value) return '';
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
        },

        apiErrorMessage(payload, fallback) {
            const validationErrors = payload && payload.errors
                ? Object.values(payload.errors).flat().filter(Boolean)
                : [];

            return validationErrors.join('\n') || payload?.message || fallback;
        },

        async saveTask() {
            if (!this.newTask.name || this.activitySaving.task) return;
            this.activitySaving.task = true;
            try {
                const isEdit = !!this.newTask.id;
                const url = isEdit ? '/api/tasks/' + this.newTask.id : '/api/tasks';
                const method = isEdit ? 'PUT' : 'POST';

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        name: this.newTask.name,
                        parent_task_id: this.newTask.parentTaskId || null,
                        start_date: this.newTask.startDate || null,
                        due_date: this.newTask.dueDate,
                        estimated_duration_minutes: this.newTask.estimatedDurationMinutes === '' ? null : Number(this.newTask.estimatedDurationMinutes),
                        recurrence: this.newTask.recurrence || null,
                        reminder_type: this.newTask.reminderType || null,
                        reminder_value: this.newTask.reminderValue === '' ? null : Number(this.newTask.reminderValue),
                        dependency_task_ids: (this.newTask.dependencyTaskIds || []).map(Number),
                        status: this.newTask.status || (this.newTask.completed ? 'Completed' : 'Open'),
                        priority: this.newTask.priority || 'Normal',
                        related_to: Array.isArray(this.newTask.relatedTo) ? this.newTask.relatedTo.join(', ') : this.newTask.relatedTo,
                        owner: this.newTask.owner,
                        assigned_user_ids: (this.newTask.assignedUserIds || []).map(Number),
                        assigned_to_user_id: Number((this.newTask.assignedUserIds || [])[0]) || null,
                        company_id: Number(this.newTask.companyId) || null, contact_id: Number(this.newTask.contactId) || null,
                        description: this.newTask.description
                    })
                });
                const task = await response.json().catch(() => ({}));
                if (!response.ok) {
                    alert(this.apiErrorMessage(task, 'Unable to save task.'));
                    return;
                }
                if (task.calendar_sync_warning) {
                    alert(task.calendar_sync_warning);
                }
                if (isEdit) {
                    const index = this.tasks.findIndex(t => t.id === task.id);
                    if (index !== -1) this.tasks[index] = task;
                } else {
                    this.tasks.unshift(task);
                }
                this.showTaskModal = false;
                this.newTask = { id: null, parentTaskId: null, name: '', dueDate: '', startDate: '', estimatedDurationMinutes: '', recurrence: '', reminderType: '', reminderValue: '', dependencyTaskIds: [], relatedAccount: '', companyId: null, contactId: null, assignedUserIds: [], relatedTo: [], description: '', priority: 'Normal', status: 'Open', completed: false, owner: (this.currentUser || this.systemUsers[0] || '') };
                this.relatedSearch.tasks = '';
                this.relatedPickerOpen.tasks = false;
                await this.fetchActivities();
            } catch (error) {
                console.error('Error saving task:', error);
                alert(error.message || 'Unable to save task.');
            } finally {
                this.activitySaving.task = false;
            }
        },

        async saveEvent() {
            if (!this.newEvent.title || this.activitySaving.event) return;
            this.activitySaving.event = true;
            try {
                const isEdit = !!this.newEvent.id;
                const url = isEdit ? '/api/events/' + this.newEvent.id : '/api/events';
                const method = isEdit ? 'PUT' : 'POST';
                const eventStartDate = this.newEvent.fromDate || '';
                const eventEndDate = this.newEvent.toDate || eventStartDate;

                if (eventStartDate && !this.newEvent.toDate) {
                    this.newEvent.toDate = eventStartDate;
                }

                const eventFrom = [eventStartDate, this.newEvent.fromTime].filter(Boolean).join(' ') || this.newEvent.from;
                const eventTo = [eventEndDate, this.newEvent.toTime].filter(Boolean).join(' ') || this.newEvent.to;

                if (
                    eventStartDate &&
                    this.newEvent.fromTime &&
                    eventEndDate &&
                    this.newEvent.toTime
                ) {
                    const start = new Date(eventStartDate + 'T' + this.newEvent.fromTime);
                    const end = new Date(eventEndDate + 'T' + this.newEvent.toTime);

                    if (
                        Number.isNaN(start.getTime()) ||
                        Number.isNaN(end.getTime()) ||
                        end <= start
                    ) {
                        alert('End date/time must be after the start date/time.');
                        return;
                    }
                }

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        title: this.newEvent.title,
                        related_account: this.newEvent.related_account,
                        company_id: Number(this.newEvent.companyId) || null, contact_id: Number(this.newEvent.contactId) || null,
                        event_type: this.newEvent.event_type,
                        from: eventFrom,
                        to: eventTo,
                        venue: this.newEvent.venue,
                        mode: this.newEvent.mode,
                        participants: this.newEvent.participants,
                        role: this.newEvent.role,
                        status: this.newEvent.status,
                        estimated_hours: this.newEvent.estimated_hours,
                        actual_hours: this.newEvent.actual_hours,
                        related_to: Array.isArray(this.newEvent.relatedTo) ? this.newEvent.relatedTo.join(', ') : this.newEvent.relatedTo,
                        host: this.newEvent.host
                    })
                });
                const event = await response.json().catch(() => ({}));
                if (!response.ok) {
                    alert(this.apiErrorMessage(event, 'Unable to save event.'));
                    return;
                }
                if (event.calendar_sync_warning) {
                    alert(event.calendar_sync_warning);
                }
                if (isEdit) {
                    const index = this.events.findIndex(e => e.id === event.id);
                    if (index !== -1) this.events[index] = event;
                } else {
                    this.events.unshift(event);
                }
                this.showEventModal = false;
                this.newEvent = { companyId: '', contactId: '',
                    id: null,
                    title: '',
                    related_account: '',
                    event_type: '',
                    from: '',
                    to: '',
                    fromDate: '',
                    fromTime: '',
                    toDate: '',
                    toTime: '',
                    venue: '',
                    mode: 'Physical',
                    participants: '',
                    role: '',
                    status: 'Scheduled',
                    estimated_hours: '',
                    actual_hours: '',
                    relatedTo: [],
                    host: (this.systemUsers[0] || '')
                };
                this.relatedSearch.events = '';
                this.relatedPickerOpen.events = false;
                await this.fetchActivities();
            } catch (error) {
                console.error('Error saving event:', error);
                alert(error.message || 'Unable to save event.');
            } finally {
                this.activitySaving.event = false;
            }
        },

        async saveCall() {
            if (this.activitySaving.call) return;
            const handledBy = this.newCall.from === '__manual__'
                ? (this.newCall.manualFrom || '')
                : (this.newCall.from || '');

            if (!this.newCall.to && !handledBy && !this.newCall.contact) return;
            this.activitySaving.call = true;

            // Build combined contact from client/contact and handled-by fields
            const toVal = this.newCall.to || '';
            const fromVal = handledBy;
            const combinedContact = toVal && fromVal ? toVal + ' / ' + fromVal : (toVal || fromVal || this.newCall.contact);
            try {
                const isEdit = !!this.newCall.id;
                const url = isEdit ? '/api/calls/' + this.newCall.id : '/api/calls';
                const method = isEdit ? 'PUT' : 'POST';

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        contact: combinedContact,
                        to: this.newCall.to,
                        from: handledBy,
                        type: this.newCall.type,
                        start_time: this.newCall.startTime,
                        start_hour: this.newCall.startHour,
                        duration: this.newCall.duration,
                        company_id: Number(this.newCall.companyId) || null, contact_id: Number(this.newCall.contactId) || null,
                        related_to: Array.isArray(this.newCall.relatedTo) ? this.newCall.relatedTo.join(', ') : this.newCall.relatedTo,
                        owner: handledBy || this.newCall.owner,
                        purpose: this.newCall.purpose,
                        agenda: this.newCall.agenda,
                        completed: this.newCall.completed || false
                    })
                });
                const call = await response.json().catch(() => ({}));
                if (!response.ok) {
                    alert(this.apiErrorMessage(call, 'Unable to save call.'));
                    return;
                }
                if (isEdit) {
                    const index = this.calls.findIndex(c => c.id === call.id);
                    if (index !== -1) this.calls[index] = call;
                } else {
                    this.calls.unshift(call);
                }
                this.showCallModal = false;
                const defaultUser = (this.systemUsers[0] || '');
                this.newCall = { companyId: '', contactId: '', id: null, contact: '', to: '', from: defaultUser, manualFrom: '', type: 'Outbound', startTime: '', startHour: '', duration: '', relatedTo: [], owner: defaultUser, agenda: '', purpose: '', completed: false };
                this.relatedSearch.calls = '';
                this.relatedPickerOpen.calls = false;
                await this.fetchActivities();
            } catch (error) {
                console.error('Error saving call:', error);
                alert(error.message || 'Unable to save call.');
            } finally {
                this.activitySaving.call = false;
            }
        },

        async saveMeeting() {
            if (!this.newMeeting.title || this.activitySaving.meeting) return;
            this.activitySaving.meeting = true;
            try {
                // Convert Estimated Duration (minutes) to the existing DB duration format
                const estimatedMinutes = Math.max(0, parseInt(this.newMeeting.estimatedMinutes) || 0);

                this.newMeeting.durationHour = Math.floor(estimatedMinutes / 60).toString();
                this.newMeeting.durationMin = (estimatedMinutes % 60).toString();

                // Determine DB-friendly duration string
                let computedDuration = '';
                const h = parseInt(this.newMeeting.durationHour) || 0;
                const m = parseInt(this.newMeeting.durationMin) || 0;
                if (h > 0) computedDuration += h + ' hr ';
                if (m > 0 || h === 0) computedDuration += m + ' mins';
                this.newMeeting.duration = computedDuration.trim();

                const meetingAttendees = [...new Set([
                    ...(Array.isArray(this.newMeeting.attendees) ? this.newMeeting.attendees : []),
                    ...(Array.isArray(this.newMeeting.internalParticipants) ? this.newMeeting.internalParticipants : []),
                    ...String(this.newMeeting.externalAttendees || '').split(',')
                ].map(attendee => String(attendee).trim()).filter(Boolean))];
                const externalAttendees = String(this.newMeeting.externalAttendees || '')
                    .split(',')
                    .map(attendee => attendee.trim())
                    .filter(attendee => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(attendee))
                    .map(email => ({ email }));

                const isEdit = !!this.newMeeting.id;
                const url = isEdit ? '/api/meetings/' + this.newMeeting.id : '/api/meetings';
                const method = isEdit ? 'PUT' : 'POST';

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        title: this.newMeeting.title,
                        meeting_type: this.newMeeting.meetingType,
                        related_account: this.newMeeting.relatedAccount,
                        company_id: Number(this.newMeeting.companyId) || null, contact_id: Number(this.newMeeting.contactId) || null,
                        related_project: this.newMeeting.relatedProject,
                        mode: this.newMeeting.mode,
                        owner: this.newMeeting.owner,
                        date: this.newMeeting.date,
                        time: this.newMeeting.time,
                        duration: this.newMeeting.duration,
                        actual_duration: this.newMeeting.actualDuration,
                        location: this.newMeeting.location,
                        attendees: meetingAttendees.length,
                        related_to: meetingAttendees.join(', '),
                        external_attendees: externalAttendees,
                        add_to_calendar: !!this.newMeeting.addToCalendar,
                        create_google_meet: this.newMeeting.mode !== 'Physical' && !!this.newMeeting.createGoogleMeet,
                        notify_external_attendees: !!this.newMeeting.notifyAttendees,
                        status: this.newMeeting.status || 'upcoming',
                        description: this.newMeeting.description,
                        has_video: this.newMeeting.hasVideo,
                        has_audio: this.newMeeting.hasAudio,
                        has_transcript: this.newMeeting.hasTranscript,
                        has_minutes: this.newMeeting.hasMinutes
                    })
                });
                const meeting = await response.json().catch(() => ({}));
                if (!response.ok) {
                    alert(this.apiErrorMessage(meeting, 'Unable to save meeting.'));
                    return;
                }

                if (!Number.isInteger(Number(meeting?.id)) || Number(meeting.id) <= 0) {
                    alert('The meeting was saved, but the server returned an invalid meeting ID. Refreshing the activity list.');
                    await this.fetchActivities();
                    return;
                }

                if (meeting.calendar_sync_warning) {
                    alert(meeting.calendar_sync_warning);
                }

                // Cancel any stale timer for this meeting and re-schedule with updated date/time
                this.cancelScheduledRecording(meeting.id);

                if (isEdit) {
                    const index = this.meetings.findIndex(m => m.id === meeting.id);
                    if (index !== -1) this.meetings[index] = meeting;
                } else {
                    this.meetings.unshift(meeting);
                }

                // Schedule recording to fire at the meeting's date+time
                this.scheduleRecording(meeting);

                this.showMeetingModal = false;
                this.recordingError = '';
                this.newMeeting = {
                    id: null,
                    title: '',
                    meetingType: 'Client Meeting',
                    relatedAccount: '',
                    companyId: null, contactId: null,
                    relatedProject: '',
                    mode: 'Online',
                    owner: (this.systemUsers[0] || ''),
                    date: '',
                    time: '',
                    duration: '',
                    durationHour: '1',
                    durationMin: '30',
                    estimatedMinutes: 90,
                    actualDuration: '',
                    location: '',
                    attendees: [],
                    internalParticipants: [],
                    externalAttendees: '',
                    description: '',
                    addToCalendar: true,
                    createGoogleMeet: true,
                    notifyAttendees: false,
                    hasVideo: false,
                    hasAudio: false,
                    hasTranscript: false,
                    hasMinutes: false,
                    status: 'upcoming'
                };
                this.relatedSearch.meetings = '';
                this.relatedPickerOpen.meetings = false;
                await this.fetchActivities();
            } catch (error) {
                console.error('Error saving meeting:', error);
                alert(error.message || 'Unable to save meeting.');
            } finally {
                this.activitySaving.meeting = false;
            }
        },

        get taskStatusValues() {
            return ['Open', 'In Progress', 'Waiting/On Hold', 'For Review', 'Completed', 'Cancelled'];
        },

        get taskBoardColumns() {
            return [
                { status: 'Open', color: 'blue' },
                { status: 'In Progress', color: 'indigo' },
                { status: 'Waiting/On Hold', color: 'amber' },
                { status: 'For Review', color: 'violet' },
                { status: 'Completed', color: 'emerald' },
                { status: 'Cancelled', color: 'slate' },
            ];
        },

        boardTasks(status) {
            const normalize = value => String(value || 'Open').trim().toLowerCase();
            return this.filteredTasksList.filter(task => normalize(task.status) === normalize(status));
        },

        get taskOutlineRows() {
            const rows = [];
            const tasks = [...this.tasks].sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0) || Number(a.id) - Number(b.id));
            const children = new Map();
            tasks.forEach(task => {
                const parent = Number(task.parent_task_id || 0);
                if (!children.has(parent)) children.set(parent, []);
                children.get(parent).push(task);
            });
            const seen = new Set();
            const append = (parentId, depth) => {
                (children.get(Number(parentId || 0)) || []).forEach(task => {
                    if (seen.has(Number(task.id))) return;
                    seen.add(Number(task.id));
                    rows.push({ task, depth, previous: rows.length ? rows[rows.length - 1].task : null });
                    append(task.id, depth + 1);
                });
            };
            append(0, 0);
            tasks.filter(task => !seen.has(Number(task.id))).forEach(task => rows.push({ task, depth: 0, previous: rows.length ? rows[rows.length - 1].task : null }));
            return rows;
        },

        async openTaskTool(tool) {
            if (tool === 'bulk' && !this.selectedTasks.length) {
                alert('Select one or more tasks first.');
                return;
            }
            this.taskToolModal = tool;
            if (tool === 'templates') {
                this.taskTemplatesLoading = true;
                try {
                    const payload = await this.taskWorkspaceRequest('/api/task-templates');
                    this.taskTemplates = payload.templates || [];
                } catch (error) {
                    alert(error.message || 'Unable to load task templates.');
                } finally {
                    this.taskTemplatesLoading = false;
                }
            }
        },

        async applyTaskBulkEdit() {
            const changes = {};
            if (this.taskBulkStatus) changes.status = this.taskBulkStatus;
            if (this.taskBulkPriority) changes.priority = this.taskBulkPriority;
            if (!Object.keys(changes).length || !this.selectedTasks.length) return;
            this.taskToolBusy = true;
            try {
                await this.taskWorkspaceRequest('/api/tasks/bulk', { method: 'PATCH', body: JSON.stringify({ task_ids: this.selectedTasks, action: 'update', changes }) });
                await this.fetchActivities();
                this.selectedTasks = [];
                this.selectAll = false;
                this.taskToolModal = null;
            } catch (error) {
                alert(error.message || 'Unable to update the selected tasks.');
            } finally {
                this.taskToolBusy = false;
            }
        },

        async archiveSelectedTasks() {
            if (!this.selectedTasks.length || !confirm('Archive the selected tasks and their descendant branches?')) return;
            this.taskToolBusy = true;
            try {
                await this.taskWorkspaceRequest('/api/tasks/bulk', { method: 'PATCH', body: JSON.stringify({ task_ids: this.selectedTasks, action: 'archive' }) });
                await this.fetchActivities();
                this.selectedTasks = [];
                this.selectAll = false;
                this.taskToolModal = null;
            } catch (error) {
                alert(error.message || 'Unable to archive the selected tasks.');
            } finally {
                this.taskToolBusy = false;
            }
        },

        async createTasksFromOutline() {
            if (!this.taskOutlineText.trim()) return;
            this.taskToolBusy = true;
            try {
                await this.taskWorkspaceRequest('/api/tasks/outline', { method: 'POST', body: JSON.stringify({ outline: this.taskOutlineText }) });
                this.taskOutlineText = '';
                await this.fetchActivities();
            } catch (error) {
                alert(error.message || 'Unable to create the task outline.');
            } finally {
                this.taskToolBusy = false;
            }
        },

        async instantiateTaskTemplate(template) {
            this.taskToolBusy = true;
            try {
                await this.taskWorkspaceRequest(`/api/task-templates/${template.id}/instantiate`, { method: 'POST', body: JSON.stringify({}) });
                await this.fetchActivities();
                this.taskToolModal = null;
            } catch (error) {
                alert(error.message || 'Unable to create tasks from this template.');
            } finally {
                this.taskToolBusy = false;
            }
        },

        async reorderSingleTask(task, parentTaskId) {
            this.taskToolBusy = true;
            try {
                await this.taskWorkspaceRequest('/api/tasks/reorder', { method: 'PATCH', body: JSON.stringify({ items: [{ id: task.id, parent_task_id: parentTaskId || null, sort_order: Number(task.sort_order || 0) }] }) });
                await this.fetchActivities();
            } catch (error) {
                alert(error.message || 'Unable to change the task hierarchy.');
            } finally {
                this.taskToolBusy = false;
            }
        },

        indentTask(item) {
            if (item.previous) this.reorderSingleTask(item.task, item.previous.id);
        },

        outdentTask(item) {
            const parent = this.tasks.find(task => Number(task.id) === Number(item.task.parent_task_id));
            this.reorderSingleTask(item.task, parent?.parent_task_id || null);
        },

        async moveTaskStatus(task, status) {
            if (!task?.id || status === task.status) return;
            try {
                const updated = await this.taskWorkspaceRequest(`/api/tasks/${task.id}`, { method: 'PUT', body: JSON.stringify({ name: task.name, status }) });
                this.mergeTaskWorkspaceRecord(updated);
                await this.fetchActivities();
            } catch (error) {
                alert(error.message || 'Unable to move the task.');
            }
        },

        get taskWorkspaceTabs() {
            return [
                { key: 'overview', label: 'Overview' },
                { key: 'subtasks', label: 'Subtasks' },
                { key: 'related', label: 'Related Activities' },
                { key: 'files', label: 'Files' },
                { key: 'time', label: 'Time' },
                { key: 'updates', label: 'Updates' },
                { key: 'activity-log', label: 'Activity Log' },
            ];
        },

        get taskWorkspaceSubtasks() {
            const taskId = Number(this.selectedTaskDetails?.id);
            const loaded = Array.isArray(this.selectedTaskDetails?.subtasks)
                ? this.selectedTaskDetails.subtasks
                : [];
            const indexed = this.tasks.filter(task => Number(task.parent_task_id) === taskId);

            return [...loaded, ...indexed]
                .filter((task, index, records) => records.findIndex(item => Number(item.id) === Number(task.id)) === index)
                .sort((left, right) => Number(left.sort_order || 0) - Number(right.sort_order || 0));
        },

        get taskWorkspaceCompletedSubtasks() {
            return this.taskWorkspaceSubtasks.filter(task => String(task.status || '').toLowerCase() === 'completed').length;
        },

        get taskWorkspaceActiveTimer() {
            const timers = this.selectedTaskDetails?.timers || [];

            return timers.find(timer => !timer.stopped_at && (
                !this.currentUserId || Number(timer.user_id) === Number(this.currentUserId)
            )) || null;
        },

        get taskWorkspaceElapsedSeconds() {
            return (this.selectedTaskDetails?.timers || []).reduce((total, timer) => total + this.taskTimerDurationSeconds(timer), 0);
        },

        get taskWorkspaceSummaryCards() {
            const task = this.selectedTaskDetails || {};
            const subtasks = this.taskWorkspaceSubtasks;
            const completed = this.taskWorkspaceCompletedSubtasks;
            const totalEstimated = [task, ...subtasks].reduce((total, item) => total + Number(item.estimated_duration_minutes || 0), 0);
            const totalActual = [task, ...subtasks].reduce((total, item) => total + Number(item.actual_duration_minutes || 0), 0);
            const progress = subtasks.length ? Math.round((completed / subtasks.length) * 100) : 0;

            return [
                { label: 'Related Account', value: task.company?.company_name || task.related_to || 'Not linked', progress: null, note: '' },
                { label: 'Lead Assigned', value: task.primary_assignee?.name || task.primary_assignee?.email || task.owner || 'Unassigned', progress: null, note: '' },
                { label: 'Status', value: task.status || 'Open', progress: null, note: '' },
                { label: 'Due Date', value: this.formatTaskDate(task.due_date), progress: null, note: '' },
                { label: 'Subtasks', value: `${completed}/${subtasks.length} completed`, progress, note: '' },
                { label: 'Estimated Total', value: this.formatTaskMinutes(totalEstimated), progress: null, note: '' },
                { label: 'Actual Total', value: this.formatTaskMinutes(totalActual || Math.round(this.taskWorkspaceElapsedSeconds / 60)), progress: null, note: `Direct task time: ${this.formatTaskMinutes(task.actual_duration_minutes || Math.round(this.taskWorkspaceElapsedSeconds / 60))}` },
            ];
        },

        get taskWorkspaceInformationFields() {
            const task = this.selectedTaskDetails || {};

            return [
                { label: 'Start Date', value: this.formatTaskDate(task.start_date) },
                { label: 'Priority', value: task.priority || 'Normal' },
                { label: 'Prepared By', value: task.prepared_by?.name || task.prepared_by?.email || task.owner || 'Not recorded' },
                { label: 'Dependencies', value: (task.blocking_tasks || task.blockingTasks || []).length ? (task.blocking_tasks || task.blockingTasks).map(item => item.name).join(', ') : 'None' },
                { label: 'System Flags', value: Object.entries(task.system_flags || {}).filter(([, value]) => value).map(([key]) => key.replaceAll('_', ' ')).join(', ') || 'None' },
            ];
        },

        get taskWorkspaceTimeFields() {
            const task = this.selectedTaskDetails || {};

            return [
                { label: 'Estimated', value: this.formatTaskMinutes(task.estimated_duration_minutes) },
                { label: 'Actual', value: this.formatTaskMinutes(task.actual_duration_minutes || Math.round(this.taskWorkspaceElapsedSeconds / 60)) },
                { label: 'Manual Entry', value: this.formatTaskMinutes(task.manual_actual_duration_minutes) },
            ];
        },

        formatTaskDate(value) {
            if (!value) return 'Not set';
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString([], {
                year: 'numeric', month: 'short', day: 'numeric'
            });
        },

        formatTaskDateTime(value) {
            if (!value) return 'Not recorded';
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString([], {
                year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
            });
        },

        formatTaskMinutes(value) {
            const minutes = Math.max(0, Number(value || 0));
            if (!minutes) return '0m';
            const hours = Math.floor(minutes / 60);
            const remainder = Math.round(minutes % 60);
            return [hours ? `${hours}h` : '', remainder ? `${remainder}m` : ''].filter(Boolean).join(' - ');
        },

        formatTaskSeconds(value) {
            const seconds = Math.max(0, Math.floor(Number(value || 0)));
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const remainder = seconds % 60;
            return [hours, minutes, remainder].map(part => String(part).padStart(2, '0')).join(':');
        },

        taskTimerDurationSeconds(timer) {
            if (!timer?.started_at) return 0;
            const startedAt = new Date(timer.started_at).getTime();
            const endpoint = timer.stopped_at
                ? new Date(timer.stopped_at).getTime()
                : timer.paused_at
                    ? new Date(timer.paused_at).getTime()
                    : this.taskWorkspaceNow;

            if (!Number.isFinite(startedAt) || !Number.isFinite(endpoint)) return 0;
            return Math.max(0, Math.floor((endpoint - startedAt) / 1000) - Number(timer.paused_seconds || 0));
        },

        async taskWorkspaceRequest(url, options = {}) {
            const headers = { Accept: 'application/json', ...(options.headers || {}) };
            if (options.body) {
                headers['Content-Type'] = 'application/json';
                headers['X-CSRF-TOKEN'] = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
            }

            const response = await fetch(url, { ...options, headers });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(this.apiErrorMessage(payload, `Task request failed (${response.status}).`));
            return payload;
        },

        async openTaskWorkspace(task) {
            if (!task?.id) return;
            this.beginActivityDetail();
            const fullTask = this.tasks.find(item => Number(item.id) === Number(task.id));
            this.selectedTaskDetails = { ...task, ...(fullTask || {}) };
            this.selectedTaskType = 'task';
            this.taskWorkspaceTab = 'overview';
            this.taskWorkspaceRelated = [];
            this.taskWorkspaceFiles = [];
            this.taskWorkspaceAudit = [];
            this.taskWorkspaceLoading = true;
            const taskId = Number(task.id);

            try {
                const [related, files, audit] = await Promise.all([
                    this.taskWorkspaceRequest(`/api/activities/TASK/${taskId}/related`),
                    this.taskWorkspaceRequest(`/api/activities/TASK/${taskId}/attachments`),
                    this.taskWorkspaceRequest(`/api/activities/TASK/${taskId}/audit-log`),
                ]);
                if (Number(this.selectedTaskDetails?.id) !== taskId) return;
                this.taskWorkspaceRelated = related.related_activities || [];
                this.taskWorkspaceFiles = files.attachments || [];
                this.taskWorkspaceAudit = audit.audit_log || [];
            } catch (error) {
                console.error('Unable to load the task workspace.', error);
            } finally {
                if (Number(this.selectedTaskDetails?.id) === taskId) this.taskWorkspaceLoading = false;
            }
        },

        closeTaskWorkspace() {
            this.selectedTaskDetails = null;
            this.selectedTaskType = null;
            this.taskWorkspaceTab = 'overview';
            this.taskWorkspaceRelated = [];
            this.taskWorkspaceFiles = [];
            this.taskWorkspaceAudit = [];
            this.taskWorkspaceLoading = false;
            this.newNoteContent = '';
            this.editingNote = null;
        },

        mergeTaskWorkspaceRecord(task) {
            if (!task?.id) return;
            const index = this.tasks.findIndex(item => Number(item.id) === Number(task.id));
            const current = index === -1 ? {} : this.tasks[index];
            const merged = { ...current, ...this.selectedTaskDetails, ...task };
            if (index === -1) this.tasks.unshift(merged);
            else this.tasks[index] = merged;
            if (Number(this.selectedTaskDetails?.id) === Number(task.id)) this.selectedTaskDetails = merged;
        },

        async runTaskTimerAction(action) {
            if (!this.selectedTaskDetails?.id || this.taskWorkspaceBusy) return;
            this.taskWorkspaceBusy = true;
            try {
                const payload = await this.taskWorkspaceRequest(`/api/tasks/${this.selectedTaskDetails.id}/timer/${action}`, {
                    method: 'POST',
                    body: JSON.stringify({})
                });
                this.mergeTaskWorkspaceRecord(payload.task || {});
            } catch (error) {
                alert(error.message || 'Unable to update the task timer.');
            } finally {
                this.taskWorkspaceBusy = false;
            }
        },

        async completeTaskFromWorkspace() {
            if (!this.selectedTaskDetails?.id || this.taskWorkspaceBusy) return;
            this.taskWorkspaceBusy = true;
            try {
                const task = await this.taskWorkspaceRequest(`/api/tasks/${this.selectedTaskDetails.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({ name: this.selectedTaskDetails.name, status: 'Completed' })
                });
                this.mergeTaskWorkspaceRecord(task);
                await this.fetchActivities();
            } catch (error) {
                alert(error.message || 'Unable to complete the task.');
            } finally {
                this.taskWorkspaceBusy = false;
            }
        },

        async duplicateTaskFromWorkspace() {
            if (!this.selectedTaskDetails?.id || this.taskWorkspaceBusy) return;
            this.taskWorkspaceBusy = true;
            try {
                const companyId = window.prompt('Target company ID (leave blank to keep current):', this.selectedTaskDetails.company_id || '');
                if (companyId === null) return;
                const targetStart = window.prompt('Target start date (YYYY-MM-DD, optional):', '');
                if (targetStart === null) return;
                const copy = await this.taskWorkspaceRequest(`/api/tasks/${this.selectedTaskDetails.id}/duplicate`, {
                    method: 'POST',
                    body: JSON.stringify({ company_id: companyId ? Number(companyId) : null, target_start_date: targetStart || null })
                });
                this.tasks.unshift(copy);
                alert('Task duplicated.');
            } catch (error) {
                alert(error.message || 'Unable to duplicate the task.');
            } finally {
                this.taskWorkspaceBusy = false;
            }
        },

        async saveTaskAsTemplateFromWorkspace() {
            if (!this.selectedTaskDetails?.id || this.taskWorkspaceBusy) return;
            this.taskWorkspaceBusy = true;
            try {
                await this.taskWorkspaceRequest(`/api/tasks/${this.selectedTaskDetails.id}/save-template`, {
                    method: 'POST',
                    body: JSON.stringify({})
                });
                alert('Task template saved.');
            } catch (error) {
                alert(error.message || 'Unable to save the task template.');
            } finally {
                this.taskWorkspaceBusy = false;
            }
        },

        editTaskFromWorkspace() {
            const task = this.selectedTaskDetails;
            this.closeTaskWorkspace();
            this.$nextTick(() => this.editTask(task));
        },

        addSubtaskFromWorkspace() {
            const parent = this.selectedTaskDetails;
            this.closeTaskWorkspace();
            this.newTask = {
                id: null,
                parentTaskId: parent.id,
                name: '',
                dueDate: parent.due_date || '',
                startDate: parent.start_date || '',
                estimatedDurationMinutes: parent.estimated_duration_minutes || '',
                recurrence: parent.recurrence || '',
                reminderType: parent.reminder_type || '',
                reminderValue: parent.reminder_value || '',
                dependencyTaskIds: (parent.blocking_tasks || []).map(item => String(item.id)),
                relatedAccount: parent.company?.company_name || parent.related_to || '',
                companyId: String(parent.company_id || ''), contactId: String(parent.contact_id || ''),
                assignedUserIds: (parent.assignees || []).map(user => String(user.id)),
                relatedTo: parent.related_to ? String(parent.related_to).split(', ').filter(Boolean) : [],
                description: '',
                priority: parent.priority || 'Normal',
                status: 'Open',
                completed: false,
                owner: parent.owner || this.currentUser || this.systemUsers[0] || ''
            };
            this.showTaskModal = true;
        },

        editTask(task) {
            this.newTask = {
                id: task.id,
                parentTaskId: task.parent_task_id || null,
                name: task.name,
                dueDate: task.due_date,
                startDate: task.start_date || '',
                estimatedDurationMinutes: task.estimated_duration_minutes || '',
                recurrence: task.recurrence || '',
                reminderType: task.reminder_type || '',
                reminderValue: task.reminder_value || '',
                dependencyTaskIds: (task.blocking_tasks || []).map(item => String(item.id)),
                relatedAccount: task.company?.company_name || task.related_to || '',
                companyId: String(task.company_id || ''), contactId: String(task.contact_id || ''),
                assignedUserIds: (task.assignees || []).map(user => String(user.id)),
                relatedTo: task.related_to ? task.related_to.split(', ').filter(Boolean) : [],
                description: task.description,
                priority: task.priority || 'Normal',
                status: task.status || 'Open',
                completed: task.status === 'Completed',
                owner: task.owner
            };
            this.relatedSearch.tasks = '';
            this.relatedPickerOpen.tasks = false;
            this.showTaskModal = true;
        },

        workspaceText(value) {
            if (!value) return '';
            if (typeof value === 'string') return value;
            return value.content || value.text || value.transcript || JSON.stringify(value, null, 2);
        },

        meetingMinutesDocument() {
            const escape = value => String(value || '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
            const title = escape(this.selectedCoreActivityDetails?.title || 'Meeting Minutes');
            const content = escape(this.meetingMinutesText).replace(/\n/g, '<br>');
            return `<!doctype html><html><head><meta charset="utf-8"><title>${title}</title><style>body{font-family:Arial,sans-serif;margin:40px;color:#1e293b;line-height:1.6}h1{font-size:22px;border-bottom:1px solid #cbd5e1;padding-bottom:12px}.meta{color:#64748b;font-size:12px;margin-bottom:24px}</style><\/head><body><h1>${title}</h1><div class="meta">${escape(this.coreActivitySubtitle)}</div><div>${content || 'No minutes recorded.'}</div><\/body><\/html>`;
        },

        printMeetingMinutes() {
            const popup = window.open('', '_blank', 'noopener,noreferrer');
            if (!popup) return alert('Allow pop-ups to print or save the minutes as PDF.');
            popup.document.write(this.meetingMinutesDocument());
            popup.document.close();
            popup.focus();
            popup.print();
        },

        downloadMeetingMinutesWord() {
            const blob = new Blob([this.meetingMinutesDocument()], { type: 'application/msword;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${String(this.selectedCoreActivityDetails?.title || 'meeting-minutes').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase() || 'meeting-minutes'}.doc`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        },

        async meetingWorkspaceRequest(url, options = {}) {
            const headers = { Accept: 'application/json', ...(options.headers || {}) };
            if (options.body) {
                headers['Content-Type'] = 'application/json';
                headers['X-CSRF-TOKEN'] = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
            }
            const response = await fetch(url, { ...options, headers });
            const payload = response.status === 204 ? null : await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(this.apiErrorMessage(payload || {}, `Meeting workspace request failed (${response.status}).`));
            return payload;
        },

        async loadMeetingWorkspace(meetingId) {
            this.meetingWorkspaceLoading = true;
            try {
                const [workspace, related, audit] = await Promise.all([
                    this.meetingWorkspaceRequest(`/api/meetings/${meetingId}/workspace`),
                    this.meetingWorkspaceRequest(`/api/activities/MEETING/${meetingId}/related`),
                    this.meetingWorkspaceRequest(`/api/activities/MEETING/${meetingId}/audit-log`),
                ]);
                if (Number(this.selectedCoreActivityDetails?.id) !== Number(meetingId)) return;
                this.meetingWorkspace = workspace || {};
                this.meetingMinutesText = this.workspaceText(workspace?.structured_minutes);
                this.meetingTranscriptText = this.workspaceText(workspace?.transcript_metadata);
                this.meetingWorkspaceRelated = related?.related_activities || [];
                this.meetingWorkspaceAudit = audit?.audit_log || [];
            } catch (error) {
                alert(error.message || 'Unable to load the meeting workspace.');
            } finally {
                this.meetingWorkspaceLoading = false;
            }
        },

        async saveMeetingWorkspaceSection(section, finalize = false) {
            const meetingId = this.selectedCoreActivityDetails?.id;
            if (!meetingId || this.meetingWorkspaceBusy) return;
            const body = section === 'minutes'
                ? { structured_minutes: { content: this.meetingMinutesText }, finalize_minutes: finalize }
                : { transcript_metadata: { text: this.meetingTranscriptText, updated_at: new Date().toISOString() } };
            this.meetingWorkspaceBusy = true;
            try {
                this.meetingWorkspace = await this.meetingWorkspaceRequest(`/api/meetings/${meetingId}/workspace`, { method: 'PUT', body: JSON.stringify(body) });
                if (section === 'minutes') this.meetingMinutesText = this.workspaceText(this.meetingWorkspace.structured_minutes);
                else this.meetingTranscriptText = this.workspaceText(this.meetingWorkspace.transcript_metadata);
            } catch (error) {
                alert(error.message || 'Unable to save the meeting workspace.');
            } finally {
                this.meetingWorkspaceBusy = false;
            }
        },

        async addMeetingActionItem() {
            const meetingId = this.selectedCoreActivityDetails?.id;
            if (!meetingId || !this.newMeetingActionItem.description.trim() || this.meetingWorkspaceBusy) return;
            this.meetingWorkspaceBusy = true;
            try {
                const item = await this.meetingWorkspaceRequest(`/api/meetings/${meetingId}/action-items`, { method: 'POST', body: JSON.stringify(this.newMeetingActionItem) });
                this.meetingWorkspace.action_items = [...(this.meetingWorkspace.action_items || []), item];
                this.newMeetingActionItem = { description: '', due_date: '', assigned_to_user_id: null };
            } catch (error) {
                alert(error.message || 'Unable to add the action item.');
            } finally {
                this.meetingWorkspaceBusy = false;
            }
        },

        async convertMeetingActionItem(item) {
            const meetingId = this.selectedCoreActivityDetails?.id;
            if (!meetingId || item.task_id || this.meetingWorkspaceBusy) return;
            this.meetingWorkspaceBusy = true;
            try {
                const updated = await this.meetingWorkspaceRequest(`/api/meetings/${meetingId}/action-items/${item.id}/convert-to-task`, { method: 'POST', body: JSON.stringify({}) });
                const index = (this.meetingWorkspace.action_items || []).findIndex(candidate => Number(candidate.id) === Number(item.id));
                if (index !== -1) this.meetingWorkspace.action_items[index] = updated;
                await this.fetchActivities();
            } catch (error) {
                alert(error.message || 'Unable to convert the action item to a task.');
            } finally {
                this.meetingWorkspaceBusy = false;
            }
        },

        async openCoreActivityDetails(type, record) {
            this.beginActivityDetail();
            this.selectedCoreActivityType = type;
            this.selectedCoreActivityDetails = record;
            this.coreActivityDetailTab = 'overview';

            if (type === 'meeting') {
                const meetingId = Number(record?.id);

                if (!Number.isInteger(meetingId) || meetingId <= 0) {
                    console.error('Invalid meeting record:', record);
                    alert('Unable to open this meeting because its record ID is invalid. Please refresh the page and try again.');
                    return;
                }

                const meeting = this.meetings.find(
                    item => Number(item.id) === meetingId
                ) || record;

                this.selectedCoreActivityDetails = meeting;
                this.selectedMeetingDetails = meeting;

                await this.loadMeetingWorkspace(meetingId);
            }
        },

        closeCoreActivityDetails() {
            if (this.selectedCoreActivityType === 'meeting') {
                this.selectedMeetingDetails = null;
            }

            this.selectedCoreActivityDetails = null;
            this.selectedCoreActivityType = null;
            this.coreActivityDetailTab = 'overview';
            this.meetingWorkspace = {};
            this.meetingWorkspaceRelated = [];
            this.meetingWorkspaceAudit = [];
            this.meetingMinutesText = '';
            this.meetingTranscriptText = '';
        },

        async completeCoreActivity() {
            if (!this.selectedCoreActivityDetails || this.coreActivityIsCompleted) return;

            const type = this.selectedCoreActivityType;

            // Preserve the existing completion behavior for non-meeting
            // activity types. Meetings are completed directly below.
            if (type !== 'meeting') {
                this.openCoreActivityEdit(true);
                return;
            }

            const record = { ...this.selectedCoreActivityDetails };
            const meetingId = Number(record.id);

            if (!Number.isInteger(meetingId) || meetingId <= 0) {
                alert('Unable to complete this meeting because its record ID is invalid.');
                return;
            }

            if (!record.title) {
                alert('Unable to complete this meeting because its title is missing.');
                return;
            }

            if (this.activitySaving.meeting) return;

            this.activitySaving.meeting = true;

            try {
                const response = await fetch(`/api/meetings/${meetingId}`, {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document
                            .querySelector('meta[name=csrf-token]')
                            .getAttribute('content')
                    },
                    body: JSON.stringify({
                        title: record.title,
                        meeting_type: record.meeting_type || record.type || null,
                        related_account: record.related_account || null,
                        related_project: record.related_project || null,
                        mode: record.mode || null,
                        owner: record.owner || null,
                        date: record.date || null,
                        time: record.time || null,
                        duration: record.duration || null,
                        actual_duration: record.actual_duration || null,
                        location: record.location || null,
                        attendees: Number(record.attendees) || 0,
                        company_id: record.company_id || null,
                        contact_id: record.contact_id || null,
                        deal_id: record.deal_id || null,
                        project_id: record.project_id || null,
                        related_to: record.related_to || null,
                        status: 'completed',
                        description: record.description || null,
                        has_video: !!record.has_video,
                        has_audio: !!record.has_audio,
                        has_transcript: !!record.has_transcript,
                        has_minutes: !!record.has_minutes
                    })
                });

                const updatedMeeting = await response.json().catch(() => ({}));

                if (!response.ok) {
                    alert(this.apiErrorMessage(
                        updatedMeeting,
                        'Unable to complete meeting.'
                    ));
                    return;
                }

                const meetingIndex = this.meetings.findIndex(
                    meeting => Number(meeting.id) === meetingId
                );

                if (meetingIndex !== -1) {
                    this.meetings[meetingIndex] = {
                        ...this.meetings[meetingIndex],
                        ...updatedMeeting,
                        status: 'completed'
                    };
                }

                this.selectedCoreActivityDetails = {
                    ...record,
                    ...updatedMeeting,
                    status: 'completed'
                };

                this.selectedMeetingDetails = this.selectedCoreActivityDetails;

                await this.fetchActivities();
                this.closeCoreActivityDetails();

                alert('Meeting completed successfully.');
            } catch (error) {
                console.error('Unable to complete meeting:', error);
                alert('Unable to complete meeting. Please try again.');
            } finally {
                this.activitySaving.meeting = false;
            }
        },
        openCoreActivityEdit(markComplete = false) {
            if (!this.selectedCoreActivityDetails) return;

            const type = this.selectedCoreActivityType;
            const record = { ...this.selectedCoreActivityDetails };
            this.closeCoreActivityDetails();

            if (type === 'event') {
                if (markComplete) record.status = 'Completed';
                this.editEvent(record);
                return;
            }

            if (type === 'call') {
                if (markComplete) record.completed = true;
                this.editCall(record);
                return;
            }

            if (type === 'meeting') {
                if (markComplete) record.status = 'completed';
                this.editMeeting(record);
            }
        },

        coreActivityValue(value) {
            if (value === null || value === undefined || value === '') return '-';
            if (typeof value === 'boolean') return value ? 'Yes' : 'No';
            if (Array.isArray(value)) {
                const values = value.map(item => this.coreActivityValue(item)).filter(item => item !== '-');
                return values.length ? values.join(' - ') : '-';
            }
            if (typeof value === 'object') {
                return this.coreActivityValue(value.name || value.company_name || value.label || value.email || value.code);
            }
            return String(value).trim() || '-';
        },

        coreActivityDateTime(date, time = null) {
            if (!date && !time) return '-';
            const dateText = String(date || '').trim();
            const dateAlreadyHasTime = /[T\s]\d{1,2}:\d{2}/.test(dateText);
            const combined = [dateText, dateAlreadyHasTime ? null : time].filter(Boolean).join(' - ');
            const parsed = new Date(String(combined).replace(' ', 'T'));
            return Number.isNaN(parsed.getTime()) ? combined : parsed.toLocaleString();
        },

        coreActivityDuration(kind = 'estimated') {
            const record = this.selectedCoreActivityDetails || {};
            const actual = kind === 'actual';

            if (this.selectedCoreActivityType === 'event') {
                const value = actual ? record.actual_hours : record.estimated_hours;
                return value === null || value === undefined || value === '' ? '-' : `${value}h`;
            }

            if (this.selectedCoreActivityType === 'call') {
                const minutes = actual ? record.duration_minutes : record.estimated_duration_minutes;
                if (minutes !== null && minutes !== undefined && minutes !== '') return this.formatActivityDuration(minutes);
                return this.coreActivityValue(actual ? record.duration : record.estimated_duration);
            }

            const minutes = actual ? record.actual_duration_minutes : record.duration_minutes;
            if (minutes !== null && minutes !== undefined && minutes !== '') return this.formatActivityDuration(minutes);
            return this.coreActivityValue(actual ? (record.actual_duration || record.actual) : record.duration);
        },

        get coreActivityTypeLabel() {
            return { event: 'Events', call: 'Calls', meeting: 'Meetings' }[this.selectedCoreActivityType] || 'Activities';
        },

        get coreActivityTitle() {
            const record = this.selectedCoreActivityDetails || {};
            return this.coreActivityValue(record.title || record.purpose || record.contact);
        },

        get coreActivityAccount() {
            const record = this.selectedCoreActivityDetails || {};
            return this.coreActivityValue(record.related_account || record.related_to || record.account || record.company);
        },

        get coreActivityPreparedBy() {
            const record = this.selectedCoreActivityDetails || {};
            return this.coreActivityValue(record.prepared_by || record.preparedBy || record.owner || record.host);
        },

        get coreActivityProject() {
            const record = this.selectedCoreActivityDetails || {};
            return this.coreActivityValue(record.related_project || record.project || record.matter);
        },

        get coreActivitySubtitle() {
            const record = this.selectedCoreActivityDetails || {};
            const descriptor = this.selectedCoreActivityType === 'event'
                ? (record.event_type || record.type)
                : this.selectedCoreActivityType === 'call'
                    ? (record.category || record.call_type || record.type)
                    : (record.meeting_type || record.type);
            const values = [descriptor, this.coreActivityAccount]
                .map(value => this.coreActivityValue(value))
                .filter(value => value !== '-');
            return values.length ? values.join(' - ') : '-';
        },

        get coreActivityStatus() {
            const record = this.selectedCoreActivityDetails || {};
            if (this.selectedCoreActivityType === 'call' && typeof record.completed === 'boolean') {
                return record.completed ? 'Completed' : 'Open';
            }
            return this.coreActivityValue(record.status);
        },

        get coreActivityIsCompleted() {
            return ['completed', 'closed', 'done'].includes(String(this.coreActivityStatus || '').toLowerCase());
        },

        get coreActivityTimerDisplay() {
            return this.coreActivityDuration('actual');
        },

        get coreActivityTabs() {
            const tabs = {
                event: ['Overview', 'Participants', 'Program', 'Event Narrative', 'Media', 'Files', 'Action Items', 'Related Activities', 'Time', 'Activity Log'],
                call: ['Overview', 'Call Parties', 'Notes', 'Follow-Up', 'Related Activities', 'Files', 'Time', 'Activity Log'],
                meeting: ['Overview', 'Participants', 'Minutes', 'Transcript', 'Recordings', 'Files', 'Action Items', 'Related Activities', 'Time', 'Activity Log']
            }[this.selectedCoreActivityType] || ['Overview'];

            return tabs.map(label => ({
                key: label.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
                label
            }));
        },

        get coreActivityActiveTabLabel() {
            return this.coreActivityTabs.find(tab => tab.key === this.coreActivityDetailTab)?.label || 'Details';
        },

        get coreActivityTabEmptyMessage() {
            return `No ${this.coreActivityActiveTabLabel.toLowerCase()} data is available in this record.`;
        },

        get coreActivityInformationTitle() {
            return { event: 'Event Information', call: 'Call Information', meeting: 'Meeting Information' }[this.selectedCoreActivityType] || 'Information';
        },

        get coreActivityParticipants() {
            const record = this.selectedCoreActivityDetails || {};
            const value = record.participants ?? record.attendees ?? record.participant_count;
            if (value !== null && value !== undefined && value !== '') return this.coreActivityValue(value);
            const participantLists = [record.internal_participants, record.external_attendees, record.participant_contact_ids]
                .filter(Array.isArray);
            if (!participantLists.length) return '-';
            return String(participantLists.reduce((total, list) => total + list.length, 0));
        },

        get coreActivitySummaryCards() {
            const record = this.selectedCoreActivityDetails || {};

            if (this.selectedCoreActivityType === 'event') {
                return [
                    { label: 'Related Account', value: this.coreActivityAccount },
                    { label: 'Event Type', value: this.coreActivityValue(record.event_type || record.type) },
                    { label: 'JK&C Role', value: this.coreActivityValue(record.role || record.jkc_role) },
                    { label: 'Participants', value: this.coreActivityParticipants },
                    { label: 'Status', value: this.coreActivityStatus },
                    { label: 'Estimated', value: this.coreActivityDuration('estimated') },
                    { label: 'Actual', value: this.coreActivityDuration('actual') }
                ];
            }

            if (this.selectedCoreActivityType === 'call') {
                const direction = record.direction || (record.type === 'Inbound' ? 'Incoming' : record.type === 'Outbound' ? 'Outgoing' : record.type);
                const hasGroupValue = record.group_call !== undefined || record.is_group_call !== undefined || record.call_scope;
                const groupValue = record.group_call ?? record.is_group_call ?? record.call_scope;
                return [
                    { label: 'Related Account', value: this.coreActivityAccount },
                    { label: 'Direction', value: this.coreActivityValue(direction) },
                    { label: 'Caller / Initiator', value: this.coreActivityValue(record.caller || record.initiator || record.owner || record.from) },
                    { label: 'Group Call', value: hasGroupValue ? this.coreActivityValue(groupValue) : '-' },
                    { label: 'Status', value: this.coreActivityStatus },
                    { label: 'Estimated', value: this.coreActivityDuration('estimated') },
                    { label: 'Actual', value: this.coreActivityDuration('actual') }
                ];
            }

            return [
                { label: 'Related Account', value: this.coreActivityAccount },
                { label: 'Mode', value: this.coreActivityValue(record.mode) },
                { label: 'Date / Time', value: this.coreActivityDateTime(record.date, record.time) },
                { label: 'Participants', value: this.coreActivityParticipants },
                { label: 'Status', value: this.coreActivityStatus },
                { label: 'Estimated', value: this.coreActivityDuration('estimated') },
                { label: 'Actual', value: this.coreActivityDuration('actual') }
            ];
        },

        get coreActivityOverviewFields() {
            const record = this.selectedCoreActivityDetails || {};

            if (this.selectedCoreActivityType === 'event') {
                return [
                    { label: 'Participation Type', value: this.coreActivityValue(record.participation_type) },
                    { label: 'Organizer', value: this.coreActivityValue(record.organizer || record.host) },
                    { label: 'Prepared By', value: this.coreActivityPreparedBy },
                    { label: 'Related Project / Matter', value: this.coreActivityProject },
                    { label: 'Event Mode', value: this.coreActivityValue(record.mode) },
                    { label: 'Venue / Platform', value: this.coreActivityValue(record.venue || record.location || record.platform) },
                    { label: 'Start', value: this.coreActivityDateTime(record.from, record.start_time || record.time) },
                    { label: 'End', value: this.coreActivityDateTime(record.to, record.end_time) },
                    { label: 'Event Outcome', value: this.coreActivityValue(record.outcome) },
                    { label: 'Description / Purpose', value: this.coreActivityValue(record.description || record.purpose), wide: true }
                ];
            }

            if (this.selectedCoreActivityType === 'call') {
                return [
                    { label: 'Call Type', value: this.coreActivityValue(record.call_type || record.category || record.type) },
                    { label: 'Prepared By', value: this.coreActivityPreparedBy },
                    { label: 'Related Project / Matter', value: this.coreActivityProject },
                    { label: 'Call Channel', value: this.coreActivityValue(record.call_channel || record.channel) },
                    { label: 'Device Used', value: this.coreActivityValue(record.device_used || record.device) },
                    { label: 'SIM / Line Used', value: this.coreActivityValue(record.sim_line_used || record.line_account || record.sim) },
                    { label: 'Number / Account Used', value: this.coreActivityValue(record.number_account_used || record.phone_number || record.account_used) },
                    { label: 'Call Outcome', value: this.coreActivityValue(record.outcome) },
                    { label: 'Date / Time', value: this.coreActivityDateTime(record.start_time, record.start_hour) },
                    { label: 'Purpose / Agenda', value: this.coreActivityValue(record.purpose || record.agenda), wide: true }
                ];
            }

            const notification = Object.prototype.hasOwnProperty.call(record, 'notify_external_attendees')
                ? (record.notify_external_attendees ? 'Enabled' : 'Not enabled')
                : '-';
            return [
                { label: 'Meeting Type', value: this.coreActivityValue(record.meeting_type || record.type) },
                { label: 'Prepared By', value: this.coreActivityPreparedBy },
                { label: 'Related Project / Matter', value: this.coreActivityProject },
                { label: 'Venue', value: this.coreActivityValue(record.location || record.venue) },
                { label: 'Email Notification', value: notification },
                { label: 'Outcome', value: this.coreActivityValue(record.outcome) },
                { label: 'Agenda / Purpose', value: this.coreActivityValue(record.agenda || record.description || record.purpose), wide: true }
            ];
        },

        get coreActivityIsTraining() {
            const record = this.selectedCoreActivityDetails || {};
            const classification = `${record.event_type || ''} ${record.type || ''} ${record.title || ''}`.toLowerCase();
            return ['training', 'seminar', 'workshop'].some(term => classification.includes(term))
                || ['training_provider', 'trainer_facilitator', 'learning_objectives', 'target_participants', 'certificates_issued']
                    .some(field => record[field] !== null && record[field] !== undefined && record[field] !== '');
        },

        get coreEventTrainingFields() {
            const record = this.selectedCoreActivityDetails || {};
            return [
                { label: 'Training Provider', value: this.coreActivityValue(record.training_provider) },
                { label: 'Trainer / Facilitator', value: this.coreActivityValue(record.trainer_facilitator || record.trainer) },
                { label: 'Training Hours', value: this.coreActivityValue(record.training_hours) },
                { label: 'Learning Objectives', value: this.coreActivityValue(record.learning_objectives), wide: true },
                { label: 'Target Participants', value: this.coreActivityValue(record.target_participants) },
                { label: 'Certificates Issued', value: this.coreActivityValue(record.certificates_issued) }
            ];
        },

        get coreMeetingHasCalendarSync() {
            const record = this.selectedCoreActivityDetails || {};
            return Boolean(record.external_calendar_event_id || record.calendar_sync_status || record.calendar_synced_at);
        },

        get coreMeetingUrl() {
            const record = this.selectedCoreActivityDetails || {};
            return record.conference_url || record.google_meet_url || record.meet_url || '';
        },

        editEvent(event) {
            const [fromDate = '', fromTime = ''] = String(event.from || '').replace('T', ' ').split(' ');
            const [toDate = '', toTime = ''] = String(event.to || '').replace('T', ' ').split(' ');

            this.newEvent = { companyId: '', contactId: '',
                id: event.id, companyId: String(event.company_id || ''), contactId: String(event.contact_id || ''),
                title: event.title || '',
                related_account: event.related_account || '',
                event_type: event.event_type || '',
                from: event.from || '',
                to: event.to || '',
                fromDate,
                fromTime: fromTime.slice(0, 5),
                toDate,
                toTime: toTime.slice(0, 5),
                venue: event.venue || '',
                mode: event.mode || 'Physical',
                participants: event.participants || '',
                role: event.role || '',
                status: event.status || 'Scheduled',
                estimated_hours: event.estimated_hours || '',
                actual_hours: event.actual_hours || '',
                relatedTo: event.related_to ? event.related_to.split(', ').filter(Boolean) : [],
                host: event.host || ''
            };
            this.relatedSearch.events = '';
            this.relatedPickerOpen.events = false;
            this.showEventModal = true;
        },

        editCall(call) {
            // Parse existing contact back into to/from if it contains ' / '
            let toVal = '', fromVal = '';
            if (call.contact && call.contact.includes(' / ')) {
                const parts = call.contact.split(' / ');
                toVal = parts[0] || '';
                fromVal = parts[1] || '';
            } else {
                toVal = call.contact || '';
            }
            this.newCall = { companyId: '', contactId: '',
                id: call.id, companyId: String(call.company_id || ''), contactId: String(call.contact_id || ''),
                contact: call.contact,
                to: toVal,
                from: fromVal,
                manualFrom: '',
                type: call.type,
                startTime: call.start_time,
                startHour: call.start_hour,
                duration: call.duration,
                relatedTo: call.related_to ? call.related_to.split(', ').filter(Boolean) : [],
                owner: call.owner,
                agenda: call.agenda,
                purpose: call.purpose,
                completed: call.completed
            };
            this.relatedSearch.calls = '';
            this.relatedPickerOpen.calls = false;
            this.showCallModal = true;
        },

        handleCallDirection(type) {
            this.newCall.type = type;
            const currentUser = this.systemUsers[0] || '';
            if (type === 'Inbound') {
                this.newCall.to = currentUser;
                this.newCall.from = '';
            } else {
                this.newCall.from = currentUser;
                this.newCall.to = '';
            }
        },

        openRelatedPicker(type) {
            this.relatedPickerOpen[type] = true;
        },

        closeRelatedPicker(type) {
            this.relatedPickerOpen[type] = false;
        },

        filteredRelatedOptions(type) {
            const query = (this.relatedSearch[type] || '').toLowerCase().trim();
            const selected = this.currentRelatedSelections(type);

            return this.relatedRecords
                .filter((option) => !selected.includes(option.label))
                .filter((option) => {
                    if (!query) return true;
                    const haystack = `${option.label} ${option.meta || ''} ${option.tokens || ''}`.toLowerCase();
                    return haystack.includes(query);
                })
                .slice(0, 8);
        },

        selectFirstRelatedOption(type) {
            const [first] = this.filteredRelatedOptions(type);
            if (first) {
                this.selectRelatedOption(type, first);
            }
        },

        selectRelatedOption(type, option) {
            this.addTag(type, option.label);

            if (type === 'calls' && option) {
                this.syncCallClientFromRelatedContact(option.label, option);
            }

            this.relatedSearch[type] = '';
            this.relatedPickerOpen[type] = false;
        },

        currentRelatedSelections(type) {
            if (type === 'calls') return this.newCall.relatedTo;
            if (type === 'events') return this.newEvent.relatedTo;
            if (type === 'meetings') return this.newMeeting.attendees;

            return this.newTask.relatedTo;
        },

        addTag(type, tag) {
            const value = (tag || '').trim();
            let list;
            if (type === 'calls') list = this.newCall.relatedTo;
            else if (type === 'events') list = this.newEvent.relatedTo;
            else if (type === 'meetings') list = this.newMeeting.attendees;
            else list = this.newTask.relatedTo;

            if (value && !list.includes(value)) {
                list.push(value);
            }

            if (type === 'calls' && value) {
                this.syncCallClientFromRelatedContact(value);
            }
        },

        relatedText(value) {
            return value && String(value).trim() ? value : '-';
        },

        contactNameFromLabel(value) {
            const label = String(value || '').trim();
            if (!label) return '';
            return label.split(' - ')[0].trim() || label;
        },

        syncCallClientFromRelatedContact(label, option = null) {
            if (option && option.type === 'contact' && option.label) {
                this.newCall.to = option.label;
                return;
            }

            const match = this.relatedRecords.find((record) => record.label === label);
            if (match && match.type === 'contact') {
                this.newCall.to = match.label;
                return;
            }

            const clientName = this.contactNameFromLabel(label);
            if (clientName) {
                this.newCall.to = clientName;
            }
        },

        async openActivityFromLink() {
            const params = new URLSearchParams(window.location.search);
            if (!params.has('activity_type') && !params.has('source_id')) return false;

            const type = String(params.get('activity_type') || '').toLowerCase();
            const sourceId = params.get('source_id') || '';
            const section = String(params.get('section') || '').toLowerCase();
            const contexts = {
                task: ['task', this.tasks],
                event: ['events', this.events],
                call: ['call', this.calls],
                meeting: ['meetings', this.meetings],
                email: ['email', this.emails],
                message: ['messages', this.messages],
                official_business_travel: ['official-business', this.officialBusinessRecords]
            };
            const context = Object.hasOwn(contexts, type) ? contexts[type] : null;
            const record = /^[1-9]\d*$/.test(sourceId)
                ? context?.[1]?.find(item => String(item.id) === sourceId)
                : null;
            if (context) this.activeTab = context[0];
            if (!record) {
                alert('This activity is unavailable. It may have been removed, access may be restricted, or loading may have failed.');
                return true;
            }

            if (['event', 'call', 'meeting'].includes(type)) {
                await this.openCoreActivityDetails(type, record);
            } else if (type === 'message') {
                await this.openMessageConversation(record);
            } else {
                await this.openActivityDetails({ ...record, type, source_id: record.id, title: record.subject || record.name || record.title });
            }
            if (type === 'official_business_travel' && section === 'completion') {
                this.officialBusinessDetailTab = 'completion';
            }
            return true;
        },
        applyCompanyPrefillFromShortcut() {
            let payload = null;

            try {
                payload = JSON.parse(localStorage.getItem('activity_company_prefill') || 'null');
            } catch (error) {
                payload = null;
            }

            if (!payload) return false;

            localStorage.removeItem('activity_company_prefill');

            const urlParams = new URLSearchParams(window.location.search);
            const requestedType = urlParams.get('open') || payload.type || 'task';
            const companyId = Number(payload.company_id) || null;
            const companyName = String(payload.company_name || '').trim();

            const companyRecord = this.messageCompanyRecords.find(
                record => Number(record.id) === companyId
            );

            const relatedAccount = companyRecord?.label || companyName;
            const today = new Date().toISOString().slice(0, 10);

            if (requestedType === 'meetings') {
                this.activeTab = 'meetings';
                this.showMeetingModal = true;
                this.newMeeting.relatedAccount = relatedAccount;
                this.newMeeting.companyId = companyId;
                this.newMeeting.date = today;
                this.newMeeting.time = new Date().toTimeString().slice(0, 5);
                return true;
            }

            if (requestedType === 'messages') {
                this.activeTab = 'messages';
                this.showNewMessage = true;
                this.newMessage.client = relatedAccount; this.newMessage.companyId = String(companyId || '');
                return true;
            }

            this.activeTab = 'task';
            this.showTaskModal = true;
            this.newTask.relatedAccount = relatedAccount;
            this.newTask.companyId = companyId;
            this.newTask.name = companyName ? `Follow up with ${companyName}` : '';

            return true;
        },
        applyContactPrefillFromShortcut() {
            let payload = null;

            try {
                payload = JSON.parse(localStorage.getItem('activity_contact_prefill') || 'null');
            } catch (error) {
                payload = null;
            }

            if (!payload) return;

            localStorage.removeItem('activity_contact_prefill');

            const urlParams = new URLSearchParams(window.location.search);
            const requestedType = urlParams.get('open') || payload.type || 'task';
            const forms = {task: this.newTask, call: this.newCall, meetings: this.newMeeting, events: this.newEvent, messages: this.newMessage, email: this.newEmail};
            const form = forms[requestedType] || this.newTask;
            form.companyId = String(payload.company_id || ''); form.contactId = String(payload.contact_id || '');
            if (requestedType === 'events') { this.activeTab = 'events'; this.showEventModal = true; return; }
            if (requestedType === 'messages') { this.activeTab = 'messages'; this.showNewMessage = true; return; }
            if (requestedType === 'email') { this.activeTab = 'email'; this.showComposeEmail = true; this.newEmail.to = payload.email || ''; return; }
            const relatedContact = payload.related_contact || payload.contact_name || '';
            const contactName = payload.contact_name || this.contactNameFromLabel(relatedContact);
            const defaultUser = this.systemUsers[0] || '';
            const today = new Date().toISOString().slice(0, 10);

            if (requestedType === 'call') {
                this.activeTab = 'call';
                this.showCallModal = true;
                this.newCall.relatedTo = relatedContact ? [relatedContact] : [];
                this.newCall.to = contactName;
                this.newCall.from = defaultUser;
                this.newCall.owner = defaultUser;
                this.newCall.type = 'Outbound';
                this.newCall.startTime = today;
                this.newCall.startHour = new Date().toTimeString().slice(0, 5);
                return;
            }

            if (requestedType === 'meetings') {
                this.activeTab = 'meetings';
                this.showMeetingModal = true;
                this.newMeeting.attendees = relatedContact ? [relatedContact] : [];
                this.newMeeting.date = today;
                this.newMeeting.time = new Date().toTimeString().slice(0, 5);
                return;
            }

            this.activeTab = 'task';
            this.showTaskModal = true;
            this.newTask.relatedTo = relatedContact ? [relatedContact] : [];
            this.newTask.name = contactName ? `Follow up with ${contactName}` : '';
        },

        removeTag(type, tag) {
            if (type === 'calls') {
                this.newCall.relatedTo = this.newCall.relatedTo.filter(t => t !== tag);
            } else if (type === 'events') {
                this.newEvent.relatedTo = this.newEvent.relatedTo.filter(t => t !== tag);
            } else if (type === 'meetings') {
                this.newMeeting.attendees = this.newMeeting.attendees.filter(t => t !== tag);
            } else {
                this.newTask.relatedTo = this.newTask.relatedTo.filter(t => t !== tag);
            }
        },

        editMeeting(meeting) {
            let parsedHr = '0';
            let parsedMin = '30';
            if (meeting.duration) {
                const hrMatch = meeting.duration.match(/(\d+)\s*hr/i);
                if (hrMatch) parsedHr = hrMatch[1];
                const minMatch = meeting.duration.match(/(\d+)\s*min/i);
                if (minMatch) parsedMin = minMatch[1];
            }

            this.newMeeting = {
                id: meeting.id,
                title: meeting.title,
                meetingType: meeting.meeting_type || 'Client Meeting',
                relatedAccount: meeting.related_account || '',
                companyId: String(meeting.company_id || ''), contactId: String(meeting.contact_id || ''),
                relatedProject: meeting.related_project || '',
                mode: meeting.mode || 'Online',
                owner: meeting.owner,
                date: meeting.date,
                time: meeting.time,
                duration: meeting.duration,
                durationHour: parsedHr,
                durationMin: parsedMin,
                estimatedMinutes: ((parseInt(parsedHr) || 0) * 60) + (parseInt(parsedMin) || 0),
                actualDuration: meeting.actual_duration || '',
                location: meeting.location,
                attendees: meeting.related_to ? meeting.related_to.split(', ').filter(Boolean) : [],
                internalParticipants: [],
                externalAttendees: (meeting.external_attendees || []).map(attendee => attendee.email || attendee).filter(Boolean).join(', '),
                description: meeting.description,
                addToCalendar: !!meeting.external_calendar_event_id,
                createGoogleMeet: !!meeting.conference_url,
                notifyAttendees: !!meeting.notify_external_attendees,
                hasVideo: meeting.has_video,
                hasAudio: meeting.has_audio,
                hasTranscript: meeting.has_transcript,
                hasMinutes: meeting.has_minutes,
                status: meeting.status
            };
            this.relatedSearch.meetings = '';
            this.relatedPickerOpen.meetings = false;
            this.showMeetingModal = true;
        },

        get officialBusinessEmployees() {
            return [...new Set(this.officialBusinessRecords.map(record => record.employee).filter(Boolean))]
                .sort((left, right) => left.localeCompare(right));
        },

        get officialBusinessClients() {
            return [...new Set(this.officialBusinessRecords.map(record => record.clients).filter(Boolean))]
                .sort((left, right) => left.localeCompare(right));
        },

        get officialBusinessDepartments() {
            return [...new Set(this.officialBusinessRecords.map(record => record.department).filter(Boolean))]
                .sort((left, right) => left.localeCompare(right));
        },

        get officialBusinessDetailTabs() {
            const record = this.selectedOfficialBusinessDetails || {};
            const completionLabel = Number(record.workflow_version || record.workflowVersion || 1) >= 3
                && record.is_client_travel
                ? 'Trip Tickets & Completion'
                : 'Completion';
            const tabs = [
                { key: 'overview', label: 'Overview' },
                { key: 'itinerary', label: 'Itinerary' },
                { key: 'files', label: 'Files' },
                { key: 'related', label: 'Related Activities' },
                { key: 'completion', label: completionLabel },
                { key: 'activity-log', label: 'Activity Log' },
            ];

            if (this.officialBusinessCanManage) {
                tabs.splice(2, 0, { key: 'expenses', label: 'Expenses' });
            }

            return tabs;
        },

        get officialBusinessSummaryCards() {
            const record = this.selectedOfficialBusinessDetails || {};
            const duration = record.duration_minutes === null || record.duration_minutes === undefined
                ? 'Not recorded'
                : this.formatActivityDuration(record.duration_minutes);

            return [
                { label: 'Traveler', value: this.officialBusinessValue(record.employee || record.employee_name) },
                { label: 'O.B. Type', value: this.officialBusinessValue(record.type) },
                { label: 'Trip Type', value: this.officialBusinessValue(record.trip_type) },
                { label: 'Clients', value: this.officialBusinessValue(record.clients || record.client_name, 'Internal / JK&C') },
                { label: 'Stops', value: String(this.officialBusinessStops(record).length) },
                { label: 'Status', value: this.officialBusinessValue(record.status) },
                { label: 'Planned Departure', value: this.officialBusinessValue(record.departure || this.officialBusinessDateTime(record.date_from, record.departure_time)) },
                { label: 'Actual Duration', value: duration },
            ];
        },

        get officialBusinessOverviewFields() {
            const record = this.selectedOfficialBusinessDetails || {};
            const work = [
                ...(Array.isArray(record.purpose_options) ? record.purpose_options : []),
                record.purpose_other,
            ].filter(Boolean);

            return [
                { label: 'Purpose', value: this.officialBusinessValue(record.purpose), wide: true },
                { label: 'What Will Be Done', value: this.officialBusinessValue(work.length ? work : record.nature_of_travel || record.remarks), wide: true },
                { label: 'Transportation', value: this.officialBusinessTransportation(record), wide: true },
                { label: 'Expected Return', value: this.officialBusinessValue(record.expectedReturn || this.officialBusinessDateTime(record.date_to, record.return_time)) },
                { label: 'Actual Departure', value: this.formatTaskDateTime(record.actual_departure || record.actualDeparture) },
                { label: 'Actual Return', value: this.formatTaskDateTime(record.actual_return || record.actualReturn) },
            ];
        },

        get officialBusinessItineraryFields() {
            const record = this.selectedOfficialBusinessDetails || {};

            return [
                { label: 'Primary Destination', value: this.officialBusinessValue(record.destination_primary || record.destination) },
                { label: 'Additional Stops', value: this.officialBusinessValue(record.additional_stops) },
                { label: 'Planned Departure', value: this.officialBusinessValue(record.departure || this.officialBusinessDateTime(record.date_from, record.departure_time)) },
                { label: 'Expected Return', value: this.officialBusinessValue(record.expectedReturn || this.officialBusinessDateTime(record.date_to, record.return_time)) },
                { label: 'Team Members', value: this.officialBusinessValue(record.team_members) },
                { label: 'Nature of Travel', value: this.officialBusinessValue(record.nature_of_travel) },
            ];
        },

        get officialBusinessExpenseFields() {
            const record = this.selectedOfficialBusinessDetails || {};

            return [
                { label: 'Estimated Expenses', value: this.officialBusinessValue(record.estimated_expenses) },
                { label: 'Client Payment Status', value: this.officialBusinessValue(record.client_payment_status) },
                { label: 'Payment Items', value: this.officialBusinessValue([...(record.client_payment_items || []), record.client_payment_other].filter(Boolean)) },
                { label: 'Amount Client Will Pay', value: this.officialBusinessValue(record.amount_client_will_pay) },
                { label: 'Travel Credit', value: this.officialBusinessValue(record.travel_credit_details) },
                { label: 'Billability', value: this.officialBusinessValue(record.travel_billability) },
            ];
        },

        get officialBusinessFiles() {
            const attachments = this.selectedOfficialBusinessDetails?.attachment_paths;

            return (Array.isArray(attachments) ? attachments : [])
                .map(attachment => {
                    const path = typeof attachment === 'string'
                        ? attachment
                        : attachment?.url || attachment?.path;
                    const name = typeof attachment === 'object'
                        ? attachment?.original_name || attachment?.name
                        : null;

                    return {
                        path,
                        url: typeof attachment === 'object' ? attachment?.url : null,
                        name: name || String(path || '').split(/[\\/]/).pop() || 'Attachment',
                    };
                })
                .filter(file => file.path);
        },

        get officialBusinessCompletionFields() {
            const record = this.selectedOfficialBusinessDetails || {};

            return [
                { label: 'Status', value: this.officialBusinessValue(record.status) },
                { label: 'Actual Duration', value: record.duration_minutes === null || record.duration_minutes === undefined ? 'Not recorded' : this.formatActivityDuration(record.duration_minutes) },
                { label: 'Actual Departure', value: this.formatTaskDateTime(record.actual_departure || record.actualDeparture) },
                { label: 'Actual Return', value: this.formatTaskDateTime(record.actual_return || record.actualReturn) },
                { label: 'Remarks', value: this.officialBusinessValue(record.remarks), wide: true },
                { label: 'Client Report Description', value: this.officialBusinessValue(record.client_report_description), wide: true },
            ];
        },

        get officialBusinessTravelState() {
            const status = String(this.selectedOfficialBusinessDetails?.status || '').toLowerCase();
            const states = {
                approved: { label: 'Approved', description: 'This request is approved. Confirm readiness before recording departure.', dotClass: 'bg-cyan-500' },
                'ready for travel': { label: 'Ready for Travel', description: 'Travel readiness is confirmed and departure may now be recorded.', dotClass: 'bg-blue-500' },
                'in progress': { label: 'Travel in Progress', description: 'The traveler is currently carrying out this official business trip.', dotClass: 'bg-violet-500' },
                ongoing: { label: 'Travel in Progress', description: 'The traveler is currently carrying out this official business trip.', dotClass: 'bg-violet-500' },
                returned: { label: 'Returned', description: 'The traveler has returned and completion details may be recorded.', dotClass: 'bg-amber-500' },
                'for completion': { label: 'Returned / For Completion', description: 'The traveler has returned and the O.B. is awaiting completion requirements.', dotClass: 'bg-amber-500' },
                completed: { label: 'Completed', description: 'This official business trip has been completed.', dotClass: 'bg-emerald-500' },
                pending: { label: 'Awaiting Approval', description: 'This request is waiting for review and approval.', dotClass: 'bg-amber-500' },
                submitted: { label: 'Awaiting Approval', description: 'This request has been submitted and is waiting for review.', dotClass: 'bg-cyan-500' },
                rejected: { label: 'Not Cleared', description: 'This request was rejected and is not cleared for travel.', dotClass: 'bg-rose-500' },
                reverted: { label: 'Reverted', description: 'This request was returned for correction and may be edited again.', dotClass: 'bg-orange-500' },
                cancelled: { label: 'Cancelled', description: 'This official business trip has been cancelled.', dotClass: 'bg-slate-400' },
            };

            return states[status] || {
                label: this.officialBusinessValue(this.selectedOfficialBusinessDetails?.status, 'Travel Status'),
                description: 'Review this record for its current official business status.',
                dotClass: 'bg-slate-400',
            };
        },

        get officialBusinessCanControlMovement() {
            const record = this.selectedOfficialBusinessDetails;

            return Boolean(record)
                && (
                    Boolean(record.can_control_travel)
                    || Boolean(record.canControlTravel)
                    || Boolean(this.officialBusinessCapabilities?.travel_movement)
                );
        },

        get officialBusinessMovementStatus() {
            const record = this.selectedOfficialBusinessDetails;

            if (!record) return 'Not Started';

            if (record.movement_status) {
                return String(record.movement_status);
            }

            if (record.movementStatus) {
                return String(record.movementStatus);
            }

            if (record.actual_return) return 'Returned';
            if (record.actual_departure) return 'Departed';

            return 'Not Started';
        },

        get officialBusinessCanReady() {
            return ['not started', ''].includes(
                String(this.officialBusinessMovementStatus || '').toLowerCase()
            ) && this.officialBusinessCanControlMovement;
        },

        get officialBusinessCanDepart() {
            return ['not started', 'ready'].includes(
                String(this.officialBusinessMovementStatus || '').toLowerCase()
            ) && this.officialBusinessCanControlMovement;
        },

        get officialBusinessCanReturn() {
            return String(this.officialBusinessMovementStatus || '').toLowerCase() === 'departed'
                && this.officialBusinessCanControlMovement;
        },
        get officialBusinessCanComplete() {
            const record = this.selectedOfficialBusinessDetails;

            return Boolean(record)
                && Number(record.workflow_version || record.workflowVersion || 1) >= 3
                && String(record.status || '').toLowerCase() === 'for completion'
                && Boolean(this.officialBusinessCanManage);
        },
        get officialBusinessCanStartCompletion() {
            const record = this.selectedOfficialBusinessDetails;
            const status = String(record?.status || '').toLowerCase();

            return Boolean(record)
                && Number(record.workflow_version || record.workflowVersion || 1) >= 3
                && String(this.officialBusinessMovementStatus || '').toLowerCase() === 'returned'
                && Boolean(record.actual_return || record.actualReturn)
                && Boolean(this.officialBusinessCanManage)
                && !['for completion', 'completed', 'cancelled'].includes(status);
        },
        get officialBusinessCanApproveSelected() {
            const record = this.selectedOfficialBusinessDetails;

            return Boolean(record)
                && Number(record.workflow_version || record.workflowVersion || 1) >= 3
                && String(record.status || '').toLowerCase() === 'submitted'
                && Boolean(this.officialBusinessCapabilities?.approve);
        },
        get officialBusinessCanEditSelected() {
            const record = this.selectedOfficialBusinessDetails;

            if (!record) return false;

            const workflowVersion = Number(record.workflow_version || record.workflowVersion || 1);
            const status = String(record.status || '').toLowerCase();

            if (workflowVersion >= 3) {
                return Boolean(record.can_edit)
                    && ['draft', 'reverted'].includes(status);
            }

            return this.officialBusinessCanManage
                || (record.isMine && status === 'pending');
        },

        officialBusinessValue(value, fallback = 'Not recorded') {
            if (Array.isArray(value)) {
                const values = value
                    .map(item => item && typeof item === 'object'
                        ? item.name || item.label || item.value || Object.values(item).filter(Boolean).join(' / ')
                        : item)
                    .filter(item => item !== null && item !== undefined && String(item).trim() !== '');
                return values.length ? values.join(', ') : fallback;
            }

            if (value && typeof value === 'object') {
                const values = Object.values(value).filter(item => item !== null && item !== undefined && String(item).trim() !== '');
                return values.length ? values.join(' / ') : fallback;
            }

            if (value === null || value === undefined || String(value).trim() === '') {
                return fallback;
            }

            return String(value);
        },

        officialBusinessDateTime(date, time) {
            return [date, time].filter(Boolean).join(' - ');
        },

        officialBusinessStops(record) {
            const additional = Array.isArray(record?.additional_stops)
                ? record.additional_stops
                : String(record?.additional_stops || '').split(/[\n,;]+/);
            const stops = [record?.destination_primary || record?.destination, ...additional]
                .map(stop => String(stop || '').trim())
                .filter(Boolean);

            return [...new Set(stops)];
        },

        officialBusinessTransportation(record) {
            const details = record?.transportation_details;
            const detailValues = Array.isArray(details)
                ? details
                : details && typeof details === 'object'
                    ? Object.values(details)
                    : [];

            return this.officialBusinessValue([record?.transportation_mode, ...detailValues].filter(Boolean));
        },

        async openOfficialBusinessDetails(record) {
            if (!record) return;

            const detailRequest = this.beginActivityDetail();

            this.selectedOfficialBusinessDetails = record;
            this.officialBusinessDetailTab = 'overview';
            this.officialBusinessRelatedActivities = [];
            this.officialBusinessAuditLog = [];
            this.officialBusinessDetailLoading = true;

            try {
                const type = 'OFFICIAL_BUSINESS_TRAVEL';
                const [relatedResponse, auditResponse] = await Promise.all([
                    fetch(`/api/activities/${type}/${record.id}/related`, { headers: { Accept: 'application/json' } }),
                    fetch(`/api/activities/${type}/${record.id}/audit-log`, { headers: { Accept: 'application/json' } }),
                ]);

                if (relatedResponse.ok) {
                    const payload = await relatedResponse.json();
                    if (detailRequest === this.activityDetailRequest) this.officialBusinessRelatedActivities = payload.related_activities || [];
                }

                if (auditResponse.ok) {
                    const payload = await auditResponse.json();
                    if (detailRequest === this.activityDetailRequest) this.officialBusinessAuditLog = payload.audit_log || [];
                }
            } catch (error) {
                console.error('Unable to load Official Business detail activity data.', error);
            } finally {
                if (Number(this.selectedOfficialBusinessDetails?.id) === Number(record.id)) {
                    this.officialBusinessDetailLoading = false;
                }
            }
        },

        async openOfficialBusinessById(masterId) {
            const id = Number(masterId || 0);
            if (!id) return;

            let record = this.officialBusinessRecords.find(item => Number(item.id) === id);
            if (!record) {
                await this.fetchOfficialBusinessRecords();
                record = this.officialBusinessRecords.find(item => Number(item.id) === id);
            }

            if (!record) {
                alert('The parent Official Business could not be opened.');
                return;
            }

            await this.openOfficialBusinessDetails(record);
        },

        async openOfficialBusinessCompletion(record) {
            await this.openOfficialBusinessDetails(record);
            this.officialBusinessDetailTab = 'completion';
        },

        officialBusinessApprovalUrl(action, record) {
            const template = this.officialBusinessApprovalUrlTemplates?.[action];
            if (!template || !record?.id) return null;

            return template.replace('__OFFICIAL_BUSINESS_TRIP__', encodeURIComponent(String(record.id)));
        },

        async submitOfficialBusinessApproval(record, action) {
            if (!record?.id || !this.officialBusinessCanApproveSelected) return;

            let remarks = '';
            if (action === 'revert') {
                remarks = window.prompt('Enter the correction reason before returning this O.B. for correction:') || '';
                if (!remarks.trim()) {
                    alert('A correction reason is required.');
                    return;
                }
            }

            try {
                const headers = {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                };
                const options = { method: 'POST', headers };

                if (action === 'revert') {
                    headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify({ remarks: remarks.trim() });
                }

                const endpoint = this.officialBusinessApprovalUrl(action, record);
                if (!endpoint) throw new Error('The Official Business approval endpoint is unavailable.');

                const response = await fetch(endpoint, options);
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || `Unable to ${action} this O.B. (${response.status}).`);
                }

                const selectedId = this.selectedOfficialBusinessDetails?.id;
                await this.fetchOfficialBusinessRecords();

                if (selectedId) {
                    this.selectedOfficialBusinessDetails = this.officialBusinessRecords.find(
                        item => Number(item.id) === Number(selectedId)
                    ) || this.selectedOfficialBusinessDetails;
                }
            } catch (error) {
                alert(error.message || `Unable to ${action} this O.B.`);
            }
        },

        approveOfficialBusiness(record) {
            return this.submitOfficialBusinessApproval(record, 'approve');
        },

        revertOfficialBusiness(record) {
            return this.submitOfficialBusinessApproval(record, 'revert');
        },

        closeOfficialBusinessDetails() {
            this.selectedOfficialBusinessDetails = null;
            this.officialBusinessDetailTab = 'overview';
            this.officialBusinessRelatedActivities = [];
            this.officialBusinessAuditLog = [];
            this.officialBusinessDetailLoading = false;
        },

        openOfficialBusinessForm(record, action = 'view') {
            if (!this.officialBusinessWorkspaceUrl || !record?.id) return;

            const url = new URL(this.officialBusinessWorkspaceUrl, window.location.origin);
            url.searchParams.set(action === 'edit' ? 'edit' : 'open', record.id);
            window.open(url.toString(), '_blank', 'noopener');
        },

        openOfficialBusinessWorkspace(record = null, action = 'view') {
            if (!this.officialBusinessWorkspaceUrl) {
                alert(this.officialBusinessRequestError || 'The Official Business workspace is unavailable.');
                return;
            }

            const url = new URL(this.officialBusinessWorkspaceUrl, window.location.origin);

            if (action === 'create') {
                url.searchParams.set('create', '1');
            } else if (record?.id) {
                url.searchParams.set('open', record.id);
            }

            window.location.assign(url.toString());
        },

        filteredTravelDeskRecords() {
            const search = (this.travelDeskSearch || '').trim().toLowerCase();

            return this.travelDeskRecords.filter(record => {
                const matchesTab = this.travelDeskTab === 'all'
                    || String(record.movement || '').toLowerCase() === this.travelDeskTab;
                const matchesSearch = !search || [
                    record.ref,
                    record.traveler,
                    record.client,
                    record.destination
                ].filter(Boolean).some(value => String(value).toLowerCase().includes(search));

                return matchesTab && matchesSearch;
            });
        },

        async departTravelDeskRecord(record) {
            if (!record?.id) return;

            try {
                const response = await fetch(`/api/official-business/${record.id}/depart`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                    },
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        payload.message
                        || `Unable to record departure (${response.status}).`
                    );
                }

                const selectedId = this.selectedOfficialBusinessDetails?.id;

                await this.fetchOfficialBusinessRecords();

                if (selectedId) {
                    this.selectedOfficialBusinessDetails =
                        this.officialBusinessRecords.find(
                            item => Number(item.id) === Number(selectedId)
                        ) || this.selectedOfficialBusinessDetails;
                }
            } catch (error) {
                alert(error.message || 'Unable to record departure.');
            }
        },

        async returnTravelDeskRecord(record) {
            if (!record?.id) return;

            try {
                const response = await fetch(`/api/official-business/${record.id}/return`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                    },
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        payload.message
                        || `Unable to record return (${response.status}).`
                    );
                }

                const selectedId = this.selectedOfficialBusinessDetails?.id;

                await this.fetchOfficialBusinessRecords();

                if (selectedId) {
                    this.selectedOfficialBusinessDetails =
                        this.officialBusinessRecords.find(
                            item => Number(item.id) === Number(selectedId)
                        ) || this.selectedOfficialBusinessDetails;
                }
            } catch (error) {
                alert(error.message || 'Unable to record return.');
            }
        },
        async completeOfficialBusiness(record) {
            if (!record?.id || !this.officialBusinessCanComplete) return;

            if (!confirm('Complete this Official Business record?')) return;

            try {
                const response = await fetch(`/api/official-business/${record.id}/complete`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                    },
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        payload.message ||
                        `Unable to complete Official Business (${response.status}).`
                    );
                }

                const selectedId = this.selectedOfficialBusinessDetails?.id;

                await this.fetchOfficialBusinessRecords();

                if (selectedId) {
                    this.selectedOfficialBusinessDetails =
                        this.officialBusinessRecords.find(
                            item => Number(item.id) === Number(selectedId)
                        ) || this.selectedOfficialBusinessDetails;
                }
            } catch (error) {
                alert(error.message || 'Unable to complete Official Business.');
            }
        },

        async requestOfficialBusinessDeletion(record) {
            if (!record?.id) return;

            if (!confirm('Submit a request to delete this Official Business record? An administrator must approve the deletion.')) {
                return;
            }

            try {
                const response = await fetch(`/api/official-business/${record.id}`, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || `Unable to request deletion (${response.status}).`);
                }

                record.deletion_request_pending = true;
                alert(payload.message || 'Deletion request submitted for admin approval.');
            } catch (error) {
                alert(error.message || 'Unable to request Official Business deletion.');
            }
        },

        filteredMyOfficialBusinessRecords() {
            const search = (this.myObSearch || '').trim().toLowerCase();

            return this.officialBusinessRecords.filter(record => {
                if (!record.isMine) return false;

                const matchesSearch = !search || [
                    record.ref,
                    record.employee,
                    record.type,
                    record.clients,
                    record.destination,
                    record.status
                ].filter(Boolean).some(value => String(value).toLowerCase().includes(search));

                const matchesStatusTab = this.myObStatusTab === 'all'
                    || record.status === this.myObStatusTab;
                const matchesStatusFilter = this.myObStatusFilter === 'all'
                    || record.status === this.myObStatusFilter;
                const matchesClient = this.myObClientFilter === 'all'
                    || String(record.clients || '').includes(this.myObClientFilter);
                const matchesType = this.myObTypeFilter === 'all'
                    || record.type === this.myObTypeFilter;

                return matchesSearch
                    && matchesStatusTab
                    && matchesStatusFilter
                    && matchesClient
                    && matchesType
                    && this.officialBusinessMatchesDateRange(record, this.myObDateFrom, this.myObDateTo);
            });
        },

        filteredDeletedOfficialBusinessRecords() {

            const search = (this.deletedObSearch || '').trim().toLowerCase();

            if (!search) {
                return this.deletedOfficialBusinessRecords;
            }

            return this.deletedOfficialBusinessRecords.filter(record => {

                return [
                    record.ref,
                    record.employee,
                    record.client,
                    record.deletedBy,
                    record.deletedOn,
                    record.previousStatus
                ]
                    .filter(Boolean)
                    .some(value =>
                        String(value).toLowerCase().includes(search)
                    );

            });

        },
        async fetchDeletedOfficialBusinessRecords() {
            if (!this.officialBusinessCanManage) {
                this.deletedOfficialBusinessRecords = [];
                return;
            }

            try {
                const response = await fetch('/api/official-business/deleted', {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        payload.message ||
                        `Unable to load deleted Official Business records (${response.status}).`
                    );
                }

                this.deletedOfficialBusinessRecords =
                    Array.isArray(payload.records) ? payload.records : [];
            } catch (error) {
                this.deletedOfficialBusinessRecords = [];
                console.error(
                    'Error fetching deleted Official Business records:',
                    error
                );
            }
        },

        async restoreDeletedOfficialBusiness(record) {
            if (!record?.id) return;

            if (!confirm(
                `Restore Official Business record ${record.ref || ('OBF-' + record.id)}?`
            )) {
                return;
            }

            try {
                const response = await fetch(
                    `/api/official-business/${record.id}/restore`,
                    {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN':
                                document.querySelector('meta[name=csrf-token]')
                                    ?.getAttribute('content') || '',
                        },
                    }
                );

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        payload.message ||
                        `Unable to restore Official Business record (${response.status}).`
                    );
                }

                await this.fetchOfficialBusinessRecords();

                alert(
                    payload.message ||
                    'Official Business record restored successfully.'
                );
            } catch (error) {
                alert(
                    error.message ||
                    'Unable to restore Official Business record.'
                );
            }
        },

        async permanentlyDeleteOfficialBusiness(record) {
            if (!record?.id) return;

            if (!confirm(
                `Permanently delete ${record.ref || ('OBF-' + record.id)}? This action cannot be undone.`
            )) {
                return;
            }

            try {
                const response = await fetch(
                    `/api/official-business/${record.id}/force`,
                    {
                        method: 'DELETE',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN':
                                document.querySelector('meta[name=csrf-token]')
                                    ?.getAttribute('content') || '',
                        },
                    }
                );

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        payload.message ||
                        `Unable to permanently delete Official Business record (${response.status}).`
                    );
                }

                await this.fetchOfficialBusinessRecords();

                alert(
                    payload.message ||
                    'Official Business record permanently deleted.'
                );
            } catch (error) {
                alert(
                    error.message ||
                    'Unable to permanently delete Official Business record.'
                );
            }
        },


        filteredOfficialBusinessRecords() {

            const search = (this.obSearch || '').trim().toLowerCase();

            return this.officialBusinessRecords.filter(record => {

                const matchesSearch = !search || [
                    record.ref,
                    record.employee,
                    record.type,
                    record.clients,
                    record.destination,
                    record.status
                ].filter(Boolean).some(value => String(value).toLowerCase().includes(search));

                const matchesStatus =
                    this.obStatusFilter === 'all' ||
                    record.status === this.obStatusFilter;

                const matchesEmployee =
                    this.obEmployeeFilter === 'all' ||
                    record.employee === this.obEmployeeFilter;

                const matchesClient =
                    this.obClientFilter === 'all' ||
                    String(record.clients || '').includes(this.obClientFilter);

                const matchesType =
                    this.obTypeFilter === 'all' ||
                    record.type === this.obTypeFilter;

                const matchesDepartment = this.obDepartmentFilter === 'all'
                    || record.department === this.obDepartmentFilter;

                return matchesSearch
                    && matchesStatus
                    && matchesEmployee
                    && matchesClient
                    && matchesType
                    && matchesDepartment
                    && this.officialBusinessMatchesDateRange(record, this.obDateFrom, this.obDateTo);
            });
        },

        officialBusinessMatchesDateRange(record, dateFrom, dateTo) {
            const recordFrom = record.dateFrom || '';
            const recordTo = record.dateTo || recordFrom;

            if (dateFrom && (!recordTo || recordTo < dateFrom)) return false;
            if (dateTo && (!recordFrom || recordFrom > dateTo)) return false;

            return true;
        },
        isTaskOverdue(task) {
            if (!task) return false;
            if (task.status === 'Completed') return false;
            if (!task.due_date) return false;

            const due = new Date(task.due_date);
            const today = new Date();

            today.setHours(0, 0, 0, 0);
            due.setHours(0, 0, 0, 0);

            return due < today;
        },

        get calendarMonthLabel() {
            const date = new Date(this.calendarDate);

            return date.toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
            });
        },

        get calendarDays() {
            const viewingDate = new Date(this.calendarDate);
            const year = viewingDate.getFullYear();
            const month = viewingDate.getMonth();

            const firstDayOfMonth = new Date(year, month, 1);
            const startDate = new Date(year, month, 1 - firstDayOfMonth.getDay());

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            return Array.from({ length: 42 }, (_, index) => {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + index);
                date.setHours(0, 0, 0, 0);

                return {
                    key: this.localDateKey(date),
                    date: new Date(date),
                    dayNumber: date.getDate(),
                    inCurrentMonth: date.getMonth() === month,
                    isToday: date.getTime() === today.getTime()
                };
            });
        },

        goToPreviousMonth() {
            const date = new Date(this.calendarDate);
            this.calendarDate = new Date(
                date.getFullYear(),
                date.getMonth() - 1,
                1
            );
        },

        goToNextMonth() {
            const date = new Date(this.calendarDate);
            this.calendarDate = new Date(
                date.getFullYear(),
                date.getMonth() + 1,
                1
            );
        },

        goToToday() {
            this.calendarDate = new Date();
        },

        localDateKey(date) {
            if (!date) return '';

            const value = date instanceof Date
                ? new Date(date)
                : this.parseLocalDate(date);

            if (!value || Number.isNaN(value.getTime())) {
                return '';
            }

            const year = value.getFullYear();
            const month = String(value.getMonth() + 1).padStart(2, '0');
            const day = String(value.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        },

        parseLocalDate(value) {
            if (!value) return null;

            if (value instanceof Date) {
                const date = new Date(value);
                return Number.isNaN(date.getTime()) ? null : date;
            }

            const stringValue = String(value).trim();

            // Parse YYYY-MM-DD as a LOCAL date rather than UTC.
            const dateOnlyMatch = stringValue.match(/^(\d{4})-(\d{2})-(\d{2})$/);

            if (dateOnlyMatch) {
                const year = Number(dateOnlyMatch[1]);
                const month = Number(dateOnlyMatch[2]) - 1;
                const day = Number(dateOnlyMatch[3]);

                const date = new Date(year, month, day);
                date.setHours(0, 0, 0, 0);

                return date;
            }

            const date = new Date(stringValue);

            if (Number.isNaN(date.getTime())) {
                return null;
            }

            return date;
        },

        tasksForCalendarDay(date) {
            if (!date) return [];

            const targetKey = this.localDateKey(date);

            if (!targetKey) {
                return [];
            }

            return this.filteredTasksList.filter(task => {
                if (!task.due_date) return false;

                const due = this.parseLocalDate(task.due_date);

                if (!due) {
                    return false;
                }

                return this.localDateKey(due) === targetKey;
            });
        },

        formatCalendarDate(date) {
            if (!date) return '';

            const parsedDate = this.parseLocalDate(date);

            if (!parsedDate) {
                return '';
            }

            return parsedDate.toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        },
        get taskCounts() {
            const mainTasks = this.tasks.filter(task => !task.parent_task_id);
            return {
                total: mainTasks.length,
                open: mainTasks.filter(t => t.status === 'Open').length,
                completed: mainTasks.filter(t => t.status === 'Completed').length,
                overdue: mainTasks.filter(t => {
                    if (t.status === 'Completed') return false;
                    if (!t.due_date) return false;
                    return new Date(t.due_date) < new Date();
                }).length
            };
        },

        get eventCounts() {
            return {
                total: this.events.length,
                upcoming: this.events.filter(e => {
                    if (!e.from) return false;
                    return new Date(e.from) > new Date();
                }).length,
                closed: this.events.filter(e => {
                    if (!e.to) return false;
                    return new Date(e.to) < new Date();
                }).length
            };
        },

        get meetingCounts() {
            return {
                total: this.meetings.length,
                upcoming: this.meetings.filter(m => m.status === 'upcoming').length,
                completed: this.meetings.filter(m => m.status === 'completed').length
            };
        },

        get callCounts() {
            return {
                total: this.calls.length,
                scheduled: this.calls.filter(c => !c.completed).length,
                overdue: this.calls.filter(c => !c.completed && c.start_time && new Date(c.start_time) < new Date()).length
            };
        },

        get messageCompanyRecords() {
            return this.relatedRecords.filter(record => record.type === 'company');
        },

        get messageClientOptions() {
            return [...new Set(this.messages
                .map(conversation => conversation.company?.company_name)
                .filter(Boolean))]
                .sort((a, b) => a.localeCompare(b));
        },

        get emailMailboxOptions() {
            return this.emailMailboxes
                .filter(mailbox => mailbox.connection_status === 'connected')
                .map(mailbox => mailbox.email_address)
                .filter(Boolean);
        },

        get emailMailboxConnection() {
            return this.emailMailboxes[0] || null;
        },

        connectMyGmail() {
            window.location.href = '/api/email/gmail/connect';
        },

        async disconnectMyGmail() {
            const response = await fetch('/api/email/gmail/disconnect', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                },
            });
            if (!response.ok) {
                this.emailComposeNotice = 'Unable to disconnect Gmail.';
                return;
            }
            await this.fetchActivities();
        },

        openComposeEmail() {
            this.emailComposeNotice = '';
            if (!this.emailMailboxOptions.length) {
                this.emailComposeNotice = 'Connect My Gmail before sending email.';
            }
            this.newEmail.fromMailbox = this.emailMailboxOptions[0] || '';
            this.showComposeEmail = true;
        },

        openEmailReply() {
            const record = this.selectedActivityRecord || this.selectedActivityDetails || {};
            const subject = record.subject || this.selectedActivityDetails?.title || '';
            this.emailComposeNotice = '';
            this.newEmail = {
                ...this.newEmail,
                fromMailbox: this.emailMailboxOptions[0] || '',
                to: record.sender_email || '',
                cc: '',
                bcc: '',
                subject: String(subject).toLowerCase().startsWith('re:') ? subject : `Re: ${subject}`,
                body: '',
                threadId: record.thread_id || '',
                inReplyTo: record.rfc_message_id || '',
                referencesHeader: [record.references_header, record.rfc_message_id].filter(Boolean).join(' '),
                replyToId: record.id || this.selectedActivityDetails?.source_id || null
            };
            this.emailAttachmentFiles = [];
            this.showComposeEmail = true;
        },

        closeComposeEmail() {
            this.showComposeEmail = false;
            this.emailComposeNotice = '';
        },

        attemptEmailCompose(action) {
            if (action !== 'send') return;
            const form = new FormData();
            form.append('from_mailbox', this.newEmail.fromMailbox || '');
            form.append('to', this.newEmail.to || '');
            form.append('cc', this.newEmail.cc || '');
            form.append('bcc', this.newEmail.bcc || '');
            form.append('subject', this.newEmail.subject || '');
            form.append('body', this.newEmail.body || '');
            if (this.newEmail.threadId) form.append('thread_id', this.newEmail.threadId);
            if (this.newEmail.inReplyTo) form.append('in_reply_to', this.newEmail.inReplyTo);
            if (this.newEmail.referencesHeader) form.append('references_header', this.newEmail.referencesHeader);
            this.emailAttachmentFiles.forEach(file => form.append('attachments[]', file));
            const endpoint = this.newEmail.replyToId
                ? `/api/email-activities/${this.newEmail.replyToId}/reply`
                : '/api/email-activities/send';
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                    'Idempotency-Key': window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`
                },
                body: form
            }).then(async response => {
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || 'Unable to queue the email.');
                this.showComposeEmail = false;
                this.emailAttachmentFiles = [];
                await this.fetchActivities();
            }).catch(error => { this.emailComposeNotice = error.message || 'Unable to queue the email.'; });
        },

        get filteredEmails() {
            const search = this.emailSearch.trim().toLowerCase();

            return this.emails.filter(item => {
                const matchesSearch = !search || [
                    item.subject,
                    item.sender_email,
                    ...(item.recipients || []),
                    item.summary,
                    item.company?.company_name
                ].filter(Boolean).some(value => String(value).toLowerCase().includes(search));
                const matchesTab = this.emailTab === 'all'
                    || (this.emailTab === 'mine' && Number(item.mailbox_owner_user_id) === Number(this.currentUserId))
                    || (this.emailTab === 'incoming' && item.direction === 'Incoming')
                    || (this.emailTab === 'outgoing' && item.direction === 'Outgoing')
                    || (this.emailTab === 'unlinked' && !item.company_id && !item.contact_id);

                return matchesSearch && matchesTab;
            });
        },

        get filteredMessages() {
            let list = [...this.messages];

            if (this.messageTab === 'mine') {
                list = list.filter(conversation =>
                    (this.currentUserId && Number(conversation.assigned_user_id) === Number(this.currentUserId))
                    || this.normalizedMessageValue(conversation.assigned_user?.email) === this.normalizedMessageValue(this.currentUser)
                );
            } else if (this.messageTab === 'unlinked') {
                list = list.filter(conversation => !conversation.is_linked);
            } else if (this.messageTab !== 'all') {
                const requestedChannel = this.messageTab === 'viber' ? 'viber business' : this.messageTab;
                list = list.filter(conversation => this.normalizedMessageValue(conversation.channel) === requestedChannel);
            }

            if (this.messageClientFilter !== 'all') {
                list = list.filter(conversation => conversation.company?.company_name === this.messageClientFilter);
            }

            if (this.messageHandlingFilter !== 'all') {
                list = list.filter(conversation =>
                    this.normalizedMessageValue(conversation.handling) === this.messageHandlingFilter
                );
            }

            const search = this.normalizedMessageValue(this.messageSearch);
            if (search) {
                list = list.filter(conversation => this.normalizedMessageValue([
                    conversation.conversation_title,
                    conversation.company?.company_name,
                    this.messageContactName(conversation.contact),
                    conversation.contact?.email,
                    conversation.contact?.phone,
                    conversation.channel,
                    conversation.line_account,
                    conversation.handling,
                    conversation.assigned_user?.name,
                    conversation.assigned_user?.email,
                    conversation.latest_entry?.body,
                    conversation.external_conversation_id,
                    JSON.stringify(conversation.technical_metadata || {})
                ].filter(Boolean).join(' ')).includes(search));
            }

            return list;
        },


        get timelineTasks() {
            let list = [...this.filteredTasksList];

            if (this.timelineScope === 'open') {
                list = list.filter(task => task.status !== 'Completed');
            }

            if (this.timelineScope === 'completed') {
                list = list.filter(task => task.status === 'Completed');
            }

            return list.sort((a, b) => {

                if (!a.due_date && !b.due_date) {
                    return String(a.name || '').localeCompare(String(b.name || ''));
                }

                if (!a.due_date) return 1;
                if (!b.due_date) return -1;

                const dateA = new Date(a.due_date);
                const dateB = new Date(b.due_date);

                if (Number.isNaN(dateA.getTime()) && Number.isNaN(dateB.getTime())) {
                    return 0;
                }

                if (Number.isNaN(dateA.getTime())) return 1;
                if (Number.isNaN(dateB.getTime())) return -1;

                return dateA - dateB;
            });
        },
        get filteredTasksList() {
    // Main Tasks only in List/Board/Calendar; retain all tasks for details and Outline Builder.
    let list = this.tasks.filter(task => !task.parent_task_id);

    // My Tasks = tasks owned by the currently logged-in user
    if (this.filterSelection.task === 'My Tasks') {
        list = list.filter(t =>
            String(t.owner || '').toLowerCase() ===
            String(this.currentUser || '').toLowerCase()
        );
    }

    // Team Tasks = tasks owned by a user in the current user's department.
    if (this.filterSelection.task === 'Team Tasks') {
        const teamOwners = new Set((this.teamUsers || []).map(user =>
            String(user || '').toLowerCase()
        ));
        list = list.filter(t => teamOwners.has(String(t.owner || '').toLowerCase()));
    }

    // Existing All/Open/Closed/Overdue dropdown
    if (this.filterSelection.task === 'Open Tasks') {
        list = list.filter(t => t.status === 'Open');
    }

    if (this.filterSelection.task === 'Closed Tasks') {
        list = list.filter(t => t.status === 'Completed');
    }

    if (this.filterSelection.task === 'Overdue Tasks') {
        list = list.filter(t =>
            t.status !== 'Completed' &&
            t.due_date &&
            new Date(t.due_date) < new Date()
        );
    }

    // V2 search
    const search = this.taskSearch.trim().toLowerCase();

    if (search) {
        list = list.filter(t => {
            const haystack = [
                t.name,
                t.status,
                t.priority,
                t.related_to,
                t.owner,
                t.description
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return haystack.includes(search);
        });
    }

    // V2 status filter
    if (this.taskStatusFilter !== 'all') {
        list = list.filter(t =>
            String(t.status || '').toLowerCase() === this.taskStatusFilter
        );
    }

    // V2 priority filter
    if (this.taskPriorityFilter !== 'all') {
        list = list.filter(t =>
            String(t.priority || '').toLowerCase() === this.taskPriorityFilter
        );
    }

    // V2 owner filter
    if (this.taskOwnerFilter !== 'all') {
        list = list.filter(t => t.owner === this.taskOwnerFilter);
    }

    // Keep parent tasks immediately before their subtasks.
    // Filtering above remains authoritative; this only changes display order.
    const visibleIds = new Set(list.map(task => Number(task.id)));
    const children = new Map();

    list.forEach(task => {
        const parentId = Number(task.parent_task_id || 0);

        // If the parent is filtered out, keep the matching child visible.
        const effectiveParentId =
            parentId && visibleIds.has(parentId)
                ? parentId
                : 0;

        if (!children.has(effectiveParentId)) {
            children.set(effectiveParentId, []);
        }

        children.get(effectiveParentId).push(task);
    });

    const ordered = [];
    const seen = new Set();

    const appendTaskBranch = parentId => {
        (children.get(Number(parentId || 0)) || []).forEach(task => {
            const taskId = Number(task.id);

            if (seen.has(taskId)) return;

            seen.add(taskId);
            ordered.push(task);

            appendTaskBranch(taskId);
        });
    };

    appendTaskBranch(0);

    // Safety fallback for malformed or circular hierarchy data.
    list.forEach(task => {
        if (!seen.has(Number(task.id))) {
            ordered.push(task);
        }
    });

    return ordered;
},
        get filteredEventsList() {
            let list = [...this.events];
            const now = new Date();

            if (this.filterSelection.events === 'Upcoming Events' || this.eventViewTab === 'upcoming') {
                list = list.filter(e => e.from && new Date(e.from) > now);
            }
            if (this.filterSelection.events === 'Closed Events' || this.eventViewTab === 'completed') {
                list = list.filter(e =>
                    String(e.status || '').toLowerCase() === 'completed'
                    || (e.to && new Date(e.to) < now)
                );
            }

            if (this.eventModeFilter !== 'all') {
                list = list.filter(e =>
                    String(e.mode || '').toLowerCase() === String(this.eventModeFilter).toLowerCase()
                );
            }

            if (this.eventStatusFilter !== 'all') {
                list = list.filter(e =>
                    String(e.status || '').toLowerCase() === String(this.eventStatusFilter).toLowerCase()
                );
            }

            const search = String(this.eventSearch || '').trim().toLowerCase();
            if (search) {
                list = list.filter(e => [
                    e.title,
                    e.event_type,
                    e.related_account,
                    e.related_to,
                    e.venue,
                    e.mode,
                    e.participants,
                    e.host,
                    e.status
                ].filter(Boolean).join(' ').toLowerCase().includes(search));
            }

            return list;
        },
        get filteredCallsList() {
            let list = [...this.calls];

            // Legacy sidebar filters
            if (this.filterSelection.call === 'Scheduled Calls') {
                list = list.filter(c => !c.completed);
            }

            if (this.filterSelection.call === 'Overdue Calls') {
                list = list.filter(c =>
                    !c.completed &&
                    c.start_time &&
                    new Date(c.start_time) < new Date()
                );
            }

            if (this.filterSelection.call === 'Inbound Calls') {
                list = list.filter(c => c.type === 'Inbound');
            }

            if (this.filterSelection.call === 'Outbound Calls') {
                list = list.filter(c => c.type === 'Outbound');
            }

            // Calls page tabs
            if (this.callViewTab === 'open') {
                list = list.filter(c => !c.completed);
            }

            if (this.callViewTab === 'completed') {
                list = list.filter(c => c.completed);
            }

            // Direction
            if (this.callDirectionFilter !== 'all') {
                list = list.filter(c =>
                    c.type === this.callDirectionFilter
                );
            }

            // Status
            if (this.callStatusFilter === 'Open') {
                list = list.filter(c => !c.completed);
            }

            if (this.callStatusFilter === 'Completed') {
                list = list.filter(c => c.completed);
            }

            // Search
            const search = (this.callSearch || '')
                .trim()
                .toLowerCase();

            if (search) {
                list = list.filter(c => {
                    const searchable = [
                        c.title,
                        c.purpose,
                        c.category,
                        c.related_account,
                        c.related_to,
                        c.contact,
                        c.to,
                        c.from,
                        c.type,
                        c.outcome
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();

                    return searchable.includes(search);
                });
            }

            return list;
        },
        get allActivitiesAccounts() {
            return [...this.unifiedActivities.reduce((accounts, activity) => {
                if (activity.account_id && activity.account) {
                    accounts.set(String(activity.account_id), { id: activity.account_id, name: activity.account });
                }
                return accounts;
            }, new Map()).values()].sort((left, right) => left.name.localeCompare(right.name));
        },

        get allActivitiesStatuses() {
            return [...new Set(this.unifiedActivities.map(activity => activity.display_status).filter(Boolean))]
                .sort((left, right) => left.localeCompare(right));
        },

        get filteredAllActivities() {
            const search = this.allActivitiesSearch.trim().toLowerCase();
            const activities = this.allActivitiesReportRows.length || this.allActivitiesReportCoverage
                ? this.allActivitiesReportRows
                : this.unifiedActivities;

            return activities.filter(activity => {
                if (search) {
                    const searchable = [
                        activity.type,
                        activity.title,
                        activity.owner,
                        activity.account,
                        activity.contact,
                        activity.related_record,
                        activity.display_status
                    ].filter(Boolean).join(' ').toLowerCase();

                    if (!searchable.includes(search)) return false;
                }

                return true;
            });
        },

        get paginatedAllActivities() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredAllActivities.slice(start, start + this.perPage);
        },

        get allActivitiesDurationMinutes() {
            return this.filteredAllActivities.reduce((total, activity) => {
                return total + (activity.duration_minutes === null ? 0 : Number(activity.duration_minutes));
            }, 0);
        },

        get allActivitiesWithDuration() {
            return this.filteredAllActivities.filter(activity => activity.duration_minutes !== null).length;
        },

        get allActivitiesTotalPages() {
            return Math.max(1, Math.ceil(this.filteredAllActivities.length / this.perPage));
        },

        get allActivitiesPaginationText() {
            const total = this.filteredAllActivities.length;
            if (!total) return 'Showing 0 to 0 of 0 activities';
            const start = ((this.currentPage - 1) * this.perPage) + 1;
            const end = Math.min(this.currentPage * this.perPage, total);
            return `Showing ${start} to ${end} of ${total} activities`;
        },

        get allVisibleActivitiesSelected() {
            return this.paginatedAllActivities.length > 0
                && this.paginatedAllActivities.every(activity => this.selectedActivities.includes(this.activityKey(activity)));
        },

        toggleVisibleActivities(checked) {
            const visibleIds = this.paginatedAllActivities.map(activity => this.activityKey(activity));
            this.selectedActivities = checked
                ? [...new Set([...this.selectedActivities, ...visibleIds])]
                : this.selectedActivities.filter(id => !visibleIds.includes(id));
        },

        activityKey(activity) {
            return String(activity?.id || '').toLowerCase();
        },

        allActivitiesReportParameters(includeSelection = false, respectActivityContext = false) {
            const parameters = {};

            if (this.allActivitiesAccountFilter) parameters.company_id = Number(this.allActivitiesAccountFilter);
            if (this.allActivitiesStatusFilter) parameters.status = this.allActivitiesStatusFilter;
            if (this.allActivitiesDateFrom) parameters.date_from = this.allActivitiesDateFrom;
            if (this.allActivitiesDateTo) parameters.date_to = this.allActivitiesDateTo;

            if (!this.allActivitiesDateFrom && !this.allActivitiesDateTo) {
                if (this.allActivitiesMonthFilter) parameters.month = this.allActivitiesMonthFilter;
                else if (this.allActivitiesDateFilter) parameters.date_preset = this.allActivitiesDateFilter;
            }

            if (respectActivityContext && this.activeTab !== 'all') {
                const activityTypeByTab = {
                    task: 'task',
                    events: 'event',
                    call: 'call',
                    meetings: 'meeting',
                    emails: 'email',
                    messages: 'message',
                    'official-business': 'official_business_travel'
                };

                const contextualActivityType = activityTypeByTab[this.activeTab];

                if (contextualActivityType) {
                    parameters.activity_types = [contextualActivityType];
                }
                if (this.activeTab === 'official-business') {
                    parameters.ob_master_ids = this.filteredOfficialBusinessRecords().map(record => Number(record.id));
                    if (this.obClientFilter !== 'all') parameters.ob_client = this.obClientFilter;
                    if (this.obDepartmentFilter !== 'all') parameters.ob_department = this.obDepartmentFilter;
                    if (this.obReportMonth && !this.obDateFrom && !this.obDateTo) parameters.month = this.obReportMonth;
                    const obReportFields = {
                        ob_reference: this.obReportReference,
                        ob_ticket_reference: this.obReportTicketReference,
                        ob_destination: this.obReportDestination,
                        ob_activity: this.obReportActivity,
                        ob_transportation: this.obReportTransportation,
                        ob_employee: this.obReportEmployee,
                        ob_department: this.obReportDepartment,
                    };
                    for (const [key, value] of Object.entries(obReportFields)) {
                        if (String(value || '').trim()) parameters[key] = String(value).trim();
                    }
                    if (this.officialBusinessCapabilities?.finance) {
                        if (this.obReportCredit) parameters.ob_credit_usage = this.obReportCredit;
                        if (this.obReportCost) parameters.ob_additional_cost = this.obReportCost;
                        if (this.obReportBillability.trim()) parameters.ob_billability = this.obReportBillability.trim();
                    }
                }
            } else if (this.allActivitiesTypeFilters.length) {
                parameters.activity_types = this.allActivitiesTypeFilters;
            }

            if (this.allActivitiesDurationFilter) parameters.duration_availability = this.allActivitiesDurationFilter;
            if (includeSelection && this.selectedActivities.length) parameters.selected_ids = [...new Set(this.selectedActivities)];

            return parameters;
        },

        reportActivity(row) {
            const type = String(row.source_activity_type || '').toLowerCase();
            const sourceActivityId = String(row.source_activity_id ?? '').trim();
            const detailSourceId = type === 'official_business_travel'
                ? String(row.source_activity_parent_id ?? row.source_activity_id ?? '').trim()
                : sourceActivityId;

            const officialBusinessSource = type === 'official_business_travel'
                ? (this.officialBusinessRecords || []).find(
                    record => String(record.id) === detailSourceId
                )
                : null;

            const unifiedSource = this.unifiedActivities.find(activity => {
                const activityType = String(
                    activity?.type
                    || activity?.activity_type
                    || ''
                ).toLowerCase();

                const activitySourceId = String(
                    activity?.source_id
                    ?? activity?.id
                    ?? ''
                );

                return activityType === type
                    && activitySourceId === detailSourceId;
            });

            const source = officialBusinessSource || unifiedSource || {};

            const canonicalStatus = type === 'official_business_travel'
                ? (
                    row.status
                    || source.display_status
                    || source.source_status
                    || source.status
                    || 'Draft'
                )
                : (
                    row.status
                    || source.display_status
                    || source.source_status
                    || source.status
                    || ''
                );

            return {
                ...source,
                id: row.id,
                source_id: type === 'official_business_travel' ? detailSourceId : row.source_activity_id,
                type,
                title: row.title,
                owner: row.assigned_to || row.prepared_by,
                account_id: row.company_id,
                account: row.company,
                contact_id: row.contact_id,
                contact: row.contact,
                project_id: row.project_id,
                deal_id: row.deal_id,
                occurred_at: row.occurred_at,
                duration_minutes: row.duration_minutes,
                status: canonicalStatus,
                display_status: canonicalStatus,
                source_status: canonicalStatus,
                client_report_description: row.client_report_description,
                attachments: row.attachments || source.attachments || []
            };
        },

        async fetchAllActivitiesReport() {
            const requestId = ++this.allActivitiesReportRequest;
            this.allActivitiesReportLoading = true;
            this.allActivitiesReportError = '';

            try {
                const response = await fetch('/api/activities/report', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify(this.allActivitiesReportParameters(false))
                });
                const dataset = await response.json();

                if (!response.ok) throw new Error(dataset.message || 'Unable to load the Activities report.');
                if (requestId !== this.allActivitiesReportRequest) return;

                this.allActivitiesReportRows = (dataset.rows || []).map(row => this.reportActivity(row));
                this.allActivitiesReportCoverage = dataset.duration_coverage || null;
                const availableIds = new Set(this.allActivitiesReportRows.map(activity => this.activityKey(activity)));
                this.selectedActivities = this.selectedActivities.filter(id => availableIds.has(id));
            } catch (error) {
                if (requestId === this.allActivitiesReportRequest) {
                    this.allActivitiesReportError = error.message || 'Unable to load the Activities report.';
                }
            } finally {
                if (requestId === this.allActivitiesReportRequest) this.allActivitiesReportLoading = false;
            }
        },

        canEditActivity(activity) {
            return ['task', 'event', 'call', 'meeting', 'message']
                .includes(String(activity?.type || '').toLowerCase());
        },

        async editActivityFromAllActivities(activity) {
            if (!activity) return;

            const type = String(activity.type || '').toLowerCase();
            const id = Number(activity.source_id);

            if (!Number.isInteger(id) || id <= 0) {
                alert('Unable to identify this activity for editing.');
                return;
            }

            if (type === 'task') {
                const record = this.tasks.find(item => Number(item.id) === id);
                if (record) this.editTask(record);
                return;
            }

            if (type === 'event') {
                const record = this.events.find(item => Number(item.id) === id);
                if (record) this.editEvent(record);
                return;
            }

            if (type === 'call') {
                const record = this.calls.find(item => Number(item.id) === id);
                if (record) this.editCall(record);
                return;
            }

            if (type === 'meeting') {
                const record = this.meetings.find(item => Number(item.id) === id);
                if (record) this.editMeeting(record);
                return;
            }

            if (type === 'message') {
                const record = this.messages.find(item => Number(item.id) === id);
                if (record) await this.openMessageConversation(record);
                return;
            }
        },
        canMarkDoneActivity(activity) {
            const type = String(activity?.type || '').toLowerCase();

            if (!['call', 'message'].includes(type)) {
                return false;
            }

            const status = String(
                activity?.display_status ||
                activity?.source_status ||
                ''
            ).trim().toLowerCase();

            return ![
                'completed',
                'done',
                'closed',
                'resolved',
                'finished'
            ].includes(status);
        },

        async markActivityDone(activity) {
            if (!this.canMarkDoneActivity(activity)) {
                return;
            }

            const type = String(activity?.type || '').toLowerCase();
            const id = Number(activity?.source_id);

            if (!Number.isInteger(id) || id <= 0) {
                alert('Unable to identify this activity.');
                return;
            }

            try {
                let endpoint = '';
                let body = {};

                if (type === 'call') {
                    const record = this.calls.find(
                        item => Number(item.id) === id
                    );

                    if (!record?.contact) {
                        alert('Unable to mark this call as done because its contact information is missing.');
                        return;
                    }

                    endpoint = `/api/calls/${id}`;
                    body = {
                        contact: record.contact,
                        completed: true
                    };
                }

                if (type === 'message') {
                    endpoint = `/api/message-conversations/${id}`;
                    body = {
                        handling: 'Completed'
                    };
                }

                const response = await fetch(endpoint, {
                    method: 'PUT',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document
                            .querySelector('meta[name=csrf-token]')
                            ?.getAttribute('content') || ''
                    },
                    body: JSON.stringify(body)
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        this.apiErrorMessage(
                            payload,
                            `Unable to mark this ${type} as done.`
                        )
                    );
                }

                await this.fetchActivities();
            } catch (error) {
                console.error('Unable to mark activity as done:', error);
                alert(error.message || 'Unable to mark the activity as done.');
            }
        },

        canDeleteCompletedActivity(activity) {
            const type = String(activity?.type || '').toLowerCase();
            const status = String(
                activity?.display_status ||
                activity?.source_status ||
                ''
            ).trim().toLowerCase();

            if (!['task', 'event', 'call', 'meeting', 'message'].includes(type)) {
                return false;
            }

            return [
                'completed',
                'done',
                'closed',
                'resolved',
                'finished'
            ].includes(status);
        },

        async deleteCompletedActivity(activity) {
            if (!this.canDeleteCompletedActivity(activity)) {
                return;
            }

            const type = String(activity?.type || '').toLowerCase();
            const id = activity?.source_id;

            if (!id) {
                alert('Unable to identify this activity.');
                return;
            }

            const endpoints = {
                task: `/api/tasks/${id}`,
                event: `/api/events/${id}`,
                call: `/api/calls/${id}`,
                meeting: `/api/meetings/${id}`,
                message: `/api/message-conversations/${id}`
            };

            const endpoint = endpoints[type];

            if (!endpoint) {
                return;
            }

            const title = activity?.title || 'this activity';

            if (!confirm(`Delete completed ${type} "${title}"?`)) {
                return;
            }

            try {
                const response = await fetch(endpoint, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || ''
                    }
                });

                const responseText = await response.text();
                let payload = null;

                if (responseText) {
                    try {
                        payload = JSON.parse(responseText);
                    } catch (_) {}
                }

                if (!response.ok) {
                    throw new Error(
                        payload?.message ||
                        payload?.error ||
                        `Unable to delete ${type}.`
                    );
                }

                await this.fetchActivities();

                if (payload?.calendar_sync_warning) {
                    alert(payload.calendar_sync_warning);
                }
            } catch (error) {
                console.error('Unable to delete completed activity:', error);
                alert(error.message || 'Unable to delete the activity.');
            }
        },

        async openActivityDetails(activity) {
            const type = String(activity?.type || '').toLowerCase();
            const sourceId = String(activity?.source_id ?? activity?.id ?? '').trim();
            const collections = {
                task: this.tasks,
                event: this.events,
                call: this.calls,
                meeting: this.meetings,
                email: this.emails,
                message: this.messages,
                official_business_travel: this.officialBusinessRecords
            };
            const records = collections[type] || [];
            const record = records.find(item => String(item.id) === sourceId) || {};

            if (type === 'task' && record.id) {
                await this.openTaskWorkspace(record);
                return;
            }

            if (type === 'official_business_travel') {
                const officialBusinessRecord = record.id
                    ? record
                    : {
                        ...activity,
                        id: Number(sourceId) || activity?.source_id || activity?.id,
                        source_id: Number(sourceId) || activity?.source_id || activity?.id
                    };

                if (!officialBusinessRecord?.id) {
                    console.error('Unable to identify Official Business record:', activity);
                    alert('Unable to identify this Official Business record. Please refresh the page and try again.');
                    return;
                }

                await this.openOfficialBusinessDetails(officialBusinessRecord);
                return;
            }

            if (['event', 'call', 'meeting'].includes(type) && record.id) {
                await this.openCoreActivityDetails(type, record);
                return;
            }

            if (type === 'message' && sourceId) {
                await this.openMessageConversation(record.id ? record : { ...activity, id: sourceId });
                return;
            }

        this.beginActivityDetail();
            this.selectedActivityDetails = { ...activity, type };
            this.selectedActivityRecord = record;
            if (type === 'email' && sourceId) {
                try {
                    const response = await fetch(`/api/email-activities/${sourceId}`);
                    const detail = await response.json();
                    if (!response.ok) throw new Error(detail.message || 'Unable to load email.');
                    const attachments = (detail.attachment_metadata || []).map(item => ({
                        id: item.attachment_id,
                        filename: item.name,
                        original_filename: item.name,
                        mime_type: item.mime_type,
                        url: item.attachment_id ? `/api/email-activities/${sourceId}/attachments/${encodeURIComponent(item.attachment_id)}` : null,
                        safe_url: item.attachment_id ? `/api/email-activities/${sourceId}/attachments/${encodeURIComponent(item.attachment_id)}` : null
                    }));
                    this.selectedActivityRecord = { ...detail, attachments };
                    this.selectedActivityDetails = { ...this.selectedActivityDetails, ...detail, attachments };
                } catch (error) {
                    console.error('Unable to load email details:', error);
                }
            }
        },

        closeActivityDetails() {
            this.selectedActivityDetails = null;
            this.selectedActivityRecord = null;
        },

        activityDetailValue(value) {
            if (value === null || value === undefined || value === '') return '-';
            if (typeof value === 'boolean') return value ? 'Yes' : 'No';
            if (Array.isArray(value)) {
                return value.map(item => this.activityDetailValue(item)).filter(Boolean).join(', ');
            }
            if (typeof value === 'object') {
                return value.name || value.label || value.email || value.code || JSON.stringify(value);
            }
            return String(value);
        },

        get activityDetailRows() {
            const activity = this.selectedActivityDetails || {};
            const record = this.selectedActivityRecord || {};
            const preparedBy = activity.prepared_by || record.prepared_by;
            const rows = [
                ['Record ID', activity.source_id ?? record.id],
                ['Status', activity.display_status || activity.source_status || record.status],
                ['Owner', activity.owner || record.owner || record.employee || record.employee_name],
                ['Prepared By', preparedBy],
                ['Related Account', activity.account || record.related_account || record.clients || record.client_name],
                ['Contact', activity.contact || record.contact],
                ['Related Record', activity.related_record || record.related_to],
                ['Date', activity.occurred_at ? this.formatActivityDate(activity.occurred_at) : (record.date || record.due_date || record.date_from)],
                ['Duration', activity.duration_minutes === null || activity.duration_minutes === undefined ? (record.duration || record.actual_duration) : this.formatActivityDuration(activity.duration_minutes)],
                ['Priority', record.priority],
                ['Start / From', record.start_time || record.from || record.date_from],
                ['End / To', record.end_time || record.to || record.due_date || record.date_to],
                ['Location', record.location || record.venue || record.destination],
                ['Mode / Direction / Channel', record.mode || record.direction || record.type || record.channel],
                ['Sender', record.sender_email],
                ['Recipients', record.recipients],
                ['CC', record.cc_recipients],
                ['Participants / Attendees', record.participants || record.attendees || record.team_members || activity.participant_contact_ids],
                ['Reference', record.ob_reference_no || record.ref || record.external_conversation_id || record.provider_message_id],
                ['Outcome', record.outcome],
                ['Description / Summary', record.description || record.purpose || record.summary || record.conversation_summary || activity.conversation_summary || activity.client_report_description, true],
                ['Email Body', record.body, true]
            ];

            return rows
                .map(([label, value, wide = false]) => ({
                    label,
                    value: this.activityDetailValue(value),
                    wide
                }))
                .filter(row => row.value !== '');
        },

        formatActivityType(type) {
            return {
                task: 'Task',
                event: 'Event',
                call: 'Call',
                meeting: 'Meeting',
                email: 'Email',
                message: 'Message',
                official_business_travel: 'Official Business Travel'
            }[type] || 'Activity';
        },

        activityIcon(type) {
            return {
                task: 'far fa-calendar-check',
                event: 'far fa-calendar-alt',
                call: 'fas fa-phone-alt',
                meeting: 'fas fa-user-friends',
                email: 'far fa-envelope',
                message: 'far fa-comment-dots',
                official_business_travel: 'fas fa-route'
            }[type] || 'far fa-circle';
        },

        formatActivityDate(value) {
            if (!value) return 'Not recorded';
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? 'Not recorded' : date.toLocaleString();
        },

        formatActivityDuration(minutes) {
            const total = Math.max(0, Number(minutes) || 0);
            const hours = Math.floor(total / 60);
            const remainingMinutes = Math.round(total % 60);
            return hours > 0 ? `${hours}h ${remainingMinutes}m` : `${remainingMinutes}m`;
        },

        activityStatusClass(status) {
            const value = (status || '').toLowerCase();
            if (['completed', 'closed', 'done', 'approved'].includes(value)) return 'bg-green-50 text-green-700';
            if (['cancelled', 'canceled', 'declined', 'failed'].includes(value)) return 'bg-red-50 text-red-700';
            if (['open', 'in progress', 'ongoing'].includes(value)) return 'bg-amber-50 text-amber-700';
            return 'bg-blue-50 text-blue-700';
        },

        get activeList() {
            if (this.activeTab === 'all') return this.filteredAllActivities;
            if (this.activeTab === 'task') return this.filteredTasksList;
            if (this.activeTab === 'events') return this.filteredEventsList;
            if (this.activeTab === 'call') return this.filteredCallsList;
            if (this.activeTab === 'meetings') return this.filteredMeetings;
            return [];
        },

        async deleteActivity(id, type) {
            if (!confirm('Are you sure you want to delete this ' + type.slice(0, -1) + '?')) return;
            try {
                const response = await fetch('/api/' + type + '/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    }
                });
                const payload = response.status === 204
                    ? {}
                    : await response.json().catch(() => ({}));
                if (response.ok) {
                    if (payload.calendar_sync_warning) {
                        alert(payload.calendar_sync_warning);
                    }
                    // If deleting a meeting, cancel any pending recording timer
                    if (type === 'meetings') {
                        this.cancelScheduledRecording(id);
                        if (this.activeRecordingMeetingId === id) this.stopAllRecording();
                    }
                    this[type] = this[type].filter(item => item.id !== id);
                    if (type === 'tasks' && this.selectedTaskDetails?.id === id) this.selectedTaskDetails = null;
                    if (type === 'meetings' && this.selectedMeetingDetails?.id === id) this.selectedMeetingDetails = null;
                } else {
                    alert(this.apiErrorMessage(payload, 'Unable to delete ' + type.slice(0, -1) + '.'));
                }
            } catch (error) {
                console.error('Error deleting ' + type + ':', error);
            }
        },

        get filteredMeetings() {
            const search = (this.meetingSearch || '').trim().toLowerCase();

            return this.meetings.filter(m => {
                const status = (m.status || '').toLowerCase();

                // All Meetings / Upcoming / Completed tabs
                if (this.meetingsSubTab === 'upcoming' && status !== 'upcoming') {
                    return false;
                }

                if (this.meetingsSubTab === 'completed' && status !== 'completed') {
                    return false;
                }

                // Search meetings
                if (search) {
                    const searchable = [
                        m.title,
                        m.type,
                        m.category,
                        m.related_account,
                        m.related_to,
                        m.owner,
                        m.description,
                        m.location
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();

                    if (!searchable.includes(search)) {
                        return false;
                    }
                }

                // Mode filter: Physical / Online / Hybrid
                if (this.meetingModeFilter !== 'all') {
                    let mode = (m.mode || '').toLowerCase();

                    // Fall back to the meeting location when the API
                    // does not yet return a dedicated mode field.
                    if (!mode) {
                        const location = (m.location || '').toLowerCase();

                        if (
                            location.includes('http://') ||
                            location.includes('https://') ||
                            location.includes('zoom') ||
                            location.includes('meet.google') ||
                            location.includes('teams.microsoft')
                        ) {
                            mode = 'online';
                        }
                    }

                    if (mode !== this.meetingModeFilter) {
                        return false;
                    }
                }

                // Status dropdown
                if (
                    this.meetingStatusFilter !== 'all' &&
                    status !== this.meetingStatusFilter
                ) {
                    return false;
                }

                // Existing documentation filters
                if (this.meetingFilters.videoRecording && !m.has_video) {
                    return false;
                }

                if (this.meetingFilters.audioRecording && !m.has_audio) {
                    return false;
                }

                if (this.meetingFilters.transcription && !m.has_transcript) {
                    return false;
                }

                if (this.meetingFilters.minutes && !m.has_minutes) {
                    return false;
                }

                return true;
            });
        },

        showExportModal: false,
        exportFormat: 'csv',
        exportFormats: [
            { value: 'csv', label: 'CSV', icon: 'fas fa-file-csv' },
            { value: 'pdf', label: 'PDF', icon: 'fas fa-file-pdf' },
            { value: 'xlsx', label: 'Excel', icon: 'fas fa-file-excel' },
            { value: 'transmittal', label: 'Transmittal', icon: 'fas fa-paper-plane' },
            { value: 'print', label: 'Print', icon: 'fas fa-print' }
        ],
        exportLoading: false,
        exportError: '',

        async exportData() {
            this.exportLoading = true;
            this.exportError = '';

            try {
                const response = await fetch('/api/activities/export', {
                    method: 'POST',
                    headers: {
                        Accept: '*/*',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        ...this.allActivitiesReportParameters(true, true),
                        format: this.exportFormat
                    })
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    throw new Error(payload.message || 'Unable to export Activities.');
                }

                const blob = await response.blob();
                const disposition = response.headers.get('content-disposition') || '';
                const match = disposition.match(/filename="?([^";]+)"?/i);
                const filename = match?.[1] || `activities-report.${this.exportFormat}`;
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
                this.showExportModal = false;
            } catch (error) {
                this.exportError = error.message || 'Unable to export Activities.';
            } finally {
                this.exportLoading = false;
            }
        },

        async saveNote(noteable, type) {
            if (!this.newNoteContent) return;
            try {
                const isEdit = !!this.editingNote;
                const url = isEdit ? '/api/notes/' + this.editingNote.id : '/api/notes';
                const method = isEdit ? 'PUT' : 'POST';

                const body = isEdit ? { content: this.newNoteContent } : {
                    content: this.newNoteContent,
                    owner: (this.systemUsers[0] || 'John Kelly'),
                    noteable_id: noteable.id,
                    noteable_type: type
                };

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: JSON.stringify(body)
                });
                const note = await response.json();

                if (isEdit) {
                    const index = noteable.notes.findIndex(n => n.id === note.id);
                    if (index !== -1) noteable.notes[index] = note;
                } else {
                    if (!noteable.notes) noteable.notes = [];
                    noteable.notes.unshift(note);
                }

                this.newNoteContent = '';
                this.editingNote = null;
            } catch (error) {
                console.error('Error saving note:', error);
            }
        },

        editNote(note) {
            this.editingNote = note;
            this.newNoteContent = note.content;
        },

        async deleteNote(noteId, noteable) {
            if (!confirm('Are you sure you want to delete this note?')) return;
            try {
                const response = await fetch('/api/notes/' + noteId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    }
                });
                if (response.ok) {
                    noteable.notes = noteable.notes.filter(n => n.id !== noteId);
                }
            } catch (error) {
                console.error('Error deleting note:', error);
            }
        },

        async uploadTranscriptFile(event, meetingId) {
            const file = event.target.files[0];
            if (!file || !meetingId) return;

            this.isUploadingTranscript = true;

            const formData = new FormData();
            formData.append('transcript', file);

            try {
                const response = await fetch(`/api/meetings/${meetingId}/upload-transcript`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Transcript upload failed.');
                }

                const updatedMeeting = await response.json();
                const index = this.meetings.findIndex(m => m.id === updatedMeeting.id);
                if (index !== -1) this.meetings[index] = updatedMeeting;
                this.selectedMeetingDetails = updatedMeeting;
                event.target.value = '';
            } catch (error) {
                alert(error.message || 'Transcript upload failed.');
            } finally {
                this.isUploadingTranscript = false;
            }
        },

        async uploadMinutesFile(event, meetingId) {
            const file = event.target.files[0];
            if (!file || !meetingId) return;

            this.isUploadingMinutes = true;

            const formData = new FormData();
            formData.append('minutes', file);

            try {
                const response = await fetch(`/api/meetings/${meetingId}/upload-minutes`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Minutes upload failed.');
                }

                const updatedMeeting = await response.json();
                const index = this.meetings.findIndex(m => m.id === updatedMeeting.id);
                if (index !== -1) this.meetings[index] = updatedMeeting;
                this.selectedMeetingDetails = updatedMeeting;
                event.target.value = '';
            } catch (error) {
                alert(error.message || 'Minutes upload failed.');
            } finally {
                this.isUploadingMinutes = false;
            }
        },

        meetingHasDocument(meeting, type) {
            if (!meeting || !Array.isArray(meeting.notes)) return false;
            const keyword = type === 'transcript' ? 'TRANSCRIPT UPLOADED:' : 'MEETING MINUTES UPLOADED:';
            return meeting.notes.some(note => String(note.content || '').includes(keyword));
        },

        async generateMeetingAI(meetingId) {
            alert('Automatic AI transcript is not enabled yet. Please upload transcript or meeting minutes manually.');
        },

        downloadFile(type, format) {
            this.activeDownloadMenu = null;

            if (type === 'video' || type === 'audio') {
                const url = type === 'video' ? this.selectedMeetingDetails?.video_url : this.selectedMeetingDetails?.audio_url;
                if (!url) {
                    alert('No ' + type + ' file available for this meeting.');
                    return;
                }
                const link = document.createElement('a');
                link.href = url;
                link.download = `${type}_${this.selectedMeetingDetails?.title?.replace(/\s+/g, '_') || 'recording'}.${format}`;
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                return;
            }

            const content = this.selectedMeetingDetails?.notes?.find(n => n.content.toLowerCase().includes(type.toLowerCase()))?.content || 'No content found.';
            const filename = `${type}_${this.selectedMeetingDetails?.title?.replace(/\s+/g, '_') || 'doc'}.${format === 'pdf' ? 'pdf' : 'docx'}`;

            if (format === 'pdf') {
                // Simulate PDF by opening a new window with content for printing
                const printWindow = window.open('', '_blank');
                printWindow.document.write('<ht' + 'ml><he' + 'ad><ti' + 'tle>' + filename + '</ti' + 'tle><sty' + 'le>body{font-family:sans-serif;padding:40px;line-height:1.6;}</sty' + 'le></he' + 'ad><bo' + 'dy><h1' + '>' + type.toUpperCase() + '</h1' + '><pre style=\'white-space: pre-wrap;\'>' + content + '</pre></bo' + 'dy></ht' + 'ml>');
                printWindow.document.close();
                printWindow.print();
            } else {
                // Generate a simple Word-compatible blob
                const blob = new Blob([content], { type: 'application/msword' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename;
                link.click();
            }
        }
});
    function videoPlayer() {
        return {
            playing: false,
            currentTime: 0,
            duration: 0,
            muted: false,
            videoSrc: '',

            init() {
                // React to meeting selection changes from parent scope
                this.$watch('selectedMeetingDetails', (meeting) => {
                    this.videoSrc = meeting?.video_url || '';
                    this.currentTime = 0;
                    this.duration = 0;
                    this.playing = false;
                    if (this.$refs.videoElement) {
                        this.$refs.videoElement.load();
                    }
                });
            },

            togglePlay() {
                let vid = this.$refs.videoElement;
                if (!vid) return;
                if (vid.paused) {
                    vid.play();
                    this.playing = true;
                } else {
                    vid.pause();
                    this.playing = false;
                }
            },
            updateTime() {
                let vid = this.$refs.videoElement;
                if (!vid) return;
                this.currentTime = vid.currentTime;
                // Wait until duration is available
                if (vid.duration && !isNaN(vid.duration)) {
                    this.duration = vid.duration;
                }
            },
            seek(event) {
                let vid = this.$refs.videoElement;
                if (!vid) return;
                let rect = event.currentTarget.getBoundingClientRect();
                let pos = (event.clientX - rect.left) / rect.width;
                vid.currentTime = pos * this.duration;
            },
            skip(seconds) {
                let vid = this.$refs.videoElement;
                if (!vid) return;
                vid.currentTime += seconds;
            },
            toggleMute() {
                let vid = this.$refs.videoElement;
                if (!vid) return;
                vid.muted = !vid.muted;
                this.muted = vid.muted;
            },
            toggleFullScreen() {
                let vid = this.$refs.videoElement;
                if (!vid) return;

                if (!document.fullscreenElement) {
                    if (vid.requestFullscreen) {
                        vid.requestFullscreen();
                    } else if (vid.webkitRequestFullscreen) { /* Safari */
                        vid.webkitRequestFullscreen();
                    } else if (vid.msRequestFullscreen) { /* IE11 */
                        vid.msRequestFullscreen();
                    }
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) { /* Safari */
                        document.webkitExitFullscreen();
                    } else if (document.msExitFullscreen) { /* IE11 */
                        document.msExitFullscreen();
                    }
                }
            },
            formatTime(seconds) {
                if (!seconds || isNaN(seconds)) return '00:00';
                let m = Math.floor(seconds / 60);
                let s = Math.floor(seconds % 60);
                return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            },
            formatDurationText(seconds) {
                if (!seconds || isNaN(seconds)) return '0 Minutes';
                let m = Math.floor(seconds / 60);
                let s = Math.floor(seconds % 60);

                if (m === 0) return `${s} Seconds`;
                if (s === 0) return `${m} Minute${m > 1 ? 's' : ''}`;
                return `${m} Min, ${s} Sec`;
            },
            downloadVideo() {
                if (!this.videoSrc) return;
                const link = document.createElement('a');
                link.href = this.videoSrc;
                link.download = 'meeting_recording.mp4';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
    }
}

    const registerActivitiesAlpineData = () => {
        Alpine.data('activitiesData', () => window.activitiesData());
        Alpine.data('videoPlayer', videoPlayer);
    };

    if (window.Alpine) {
        registerActivitiesAlpineData();
    } else {
        document.addEventListener('alpine:init', registerActivitiesAlpineData, { once: true });
    }

</script>

{{-- =========================================================
     FLOATING MEETING NOTIFICATION CARD
     Appears when a scheduled meeting's time arrives.
     The "Start Recording" button provides the user gesture required for getDisplayMedia.
========================================================= --}}
<div x-data
     x-show="$store.app ? false : false"
     style="display:none"></div>

<template x-if="recordingPromptMeeting">
    <div class="fixed bottom-6 right-6 z-[200] w-80 bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0">

        {{-- Header --}}
        <div class="bg-gradient-to-r from-[#1d54e2] to-[#3b6ef5] px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                <span class="text-white text-xs font-bold uppercase tracking-widest">Meeting Starting Now</span>
            </div>
            <button @click="dismissRecordingPrompt()" class="text-white/70 hover:text-white transition text-base leading-none">&times;</button>
        </div>

        {{-- Body --}}
        <div class="px-4 py-3">
            <p class="text-sm font-semibold text-gray-900 mb-0.5" x-text="recordingPromptMeeting?.title"></p>
            <p class="text-xs text-gray-500 mb-3">
                <span x-text="recordingPromptMeeting?.date"></span>
                &nbsp;&bull;&nbsp;
                <span x-text="recordingPromptMeeting?.time"></span>
            </p>

            {{-- Feature indicators --}}
            <div class="flex flex-wrap gap-1.5 mb-4">
                <template x-if="recordingPromptMeeting?.has_video">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-semibold border border-blue-100">
                        <i class="fas fa-video text-[9px]"></i> Video
                    </span>
                </template>
                <template x-if="recordingPromptMeeting?.has_audio">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-semibold border border-blue-100">
                        <i class="fas fa-microphone text-[9px]"></i> Audio
                    </span>
                </template>
                <template x-if="recordingPromptMeeting?.has_transcript">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-50 text-purple-600 rounded-full text-[10px] font-semibold border border-purple-100">
                        <i class="fas fa-align-left text-[9px]"></i> Transcript
                    </span>
                </template>
                <template x-if="recordingPromptMeeting?.has_minutes">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-50 text-purple-600 rounded-full text-[10px] font-semibold border border-purple-100">
                        <i class="fas fa-file-alt text-[9px]"></i> Minutes
                    </span>
                </template>
            </div>

            {{-- Error (if any from previous attempt) --}}
            <div x-show="recordingError" class="mb-3 px-2 py-1.5 bg-red-50 border border-red-100 rounded-lg">
                <p class="text-[10px] text-red-600" x-text="recordingError"></p>
            </div>

            {{-- Action buttons --}}
            <div class="flex gap-2">
                <button @click="confirmStartRecording()"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 bg-[#1d54e2] text-white text-xs font-bold rounded-xl hover:bg-[#1541b0] transition shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-red-400 animate-pulse"></span>
                    Start Recording
                </button>
                <button @click="dismissRecordingPrompt()"
                        class="px-3 py-2 text-xs text-gray-500 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-xl transition font-medium">
                    Skip
                </button>
            </div>

            <p class="text-[9px] text-gray-400 text-center mt-2">You'll be asked to select your Google Meet tab</p>
        </div>
    </div>
</template>


{{-- =========================================================
     ACTIVE RECORDING STATUS BAR
     Shown while recording/transcribing is in progress.
========================================================= --}}
<template x-if="recordingVideoActive || recordingAudioActive || transcribingActive">
    <div class="fixed bottom-0 left-0 right-0 z-[190] bg-gray-900/95 backdrop-blur-sm px-6 py-3 flex items-center justify-between shadow-2xl"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0">

        <div class="flex items-center gap-4">
            {{-- Live pulse --}}
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></span>
                <span class="text-white text-xs font-bold uppercase tracking-wider">Recording</span>
            </div>
            {{-- Active streams --}}
            <div class="flex items-center gap-3 text-[10px] text-gray-400">
                <span x-show="recordingVideoActive" class="flex items-center gap-1">
                    <i class="fas fa-video text-blue-400"></i> Video
                </span>
                <span x-show="recordingAudioActive" class="flex items-center gap-1">
                    <i class="fas fa-microphone text-blue-400"></i> Audio
                </span>
                <span x-show="transcribingActive" class="flex items-center gap-1">
                    <i class="fas fa-align-left text-purple-400"></i> Transcribing
                </span>
            </div>
            {{-- Elapsed time --}}
            <span class="text-gray-300 text-xs font-mono"
                  x-text="`${String(Math.floor(recordingElapsed/3600)).padStart(2,'0')}:${String(Math.floor((recordingElapsed%3600)/60)).padStart(2,'0')}:${String(recordingElapsed%60).padStart(2,'0')}`">
            </span>
        </div>

        {{-- Live transcript preview --}}
        <div x-show="transcribingActive && liveTranscript" class="hidden md:block flex-1 mx-6 px-3 py-1 bg-white/5 rounded-lg overflow-hidden">
            <p class="text-[10px] text-gray-400 truncate" x-text="liveTranscript.slice(-120)"></p>
        </div>

        {{-- Stop button --}}
        <button @click="stopAllAndFinalize()"
                class="inline-flex items-center gap-2 px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg transition">
            <i class="fas fa-stop text-[9px]"></i> Stop & Save
        </button>
    </div>
</template>

</div><!-- end activitiesData root -->

@endsection
