@auth
    @php
        $hasNotificationsTable = \Illuminate\Support\Facades\Schema::hasTable('notifications');
        $initialNotifications = ($hasNotificationsTable && auth()->user())
            ? auth()->user()
                ->unreadNotifications()
                ->latest()
                ->take(10)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Notification',
                    'message' => $notification->data['message'] ?? ($notification->data['body'] ?? ''),
                    'url' => $notification->data['url'] ?? ($notification->data['action_url'] ?? ($notification->data['link'] ?? '#')),
                    'module' => $notification->data['module'] ?? ($notification->data['module_name'] ?? 'System'),
                    'icon' => $notification->data['icon'] ?? 'fa-bell',
                    'button_label' => $notification->data['button_label'] ?? 'Open',
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at?->diffForHumans(),
                ])
                ->values()
            : collect();

        $initialUnreadCount = ($hasNotificationsTable && auth()->user()) ? auth()->user()->unreadNotifications()->count() : 0;
    @endphp

    <div
        x-data="notificationBell({
            userId: {{ auth()->id() }},
            initialUnreadCount: {{ $initialUnreadCount }},
            initialNotifications: @js($initialNotifications),
        })"
        x-init="init()"
        class="relative"
    >
        <button
            type="button"
            @click="open = !open; fetchUnreadNotifications()"
            class="relative h-9 w-9 rounded-full hover:bg-gray-100 text-gray-500 flex items-center justify-center transition"
            aria-label="Notifications"
        >
            <i class="far fa-bell text-lg"></i>

            <span
                x-show="unreadCount > 0"
                x-text="unreadCount > 9 ? '9+' : unreadCount"
                class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center"
                style="display:none;"
            ></span>
        </button>

        <div
            x-show="open"
            @click.outside="open = false"
            x-transition
            class="absolute right-0 mt-2 w-96 max-w-[calc(100vw-2rem)] bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-[999]"
            style="display:none;"
        >
            <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Notifications</div>
                    <div class="text-xs text-gray-500" x-text="unreadCount + ' unread notification(s)'"></div>
                </div>

                <div class="flex-1"></div>

                <button
                    type="button"
                    @click="markAllAsRead()"
                    class="text-xs font-semibold text-blue-600 hover:text-blue-700"
                    x-show="unreadCount > 0"
                >
                    Mark all read
                </button>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <template x-if="notifications.length === 0">
                    <div class="px-4 py-8 text-center text-sm text-gray-500">
                        No unread notifications.
                    </div>
                </template>

                <template x-for="notification in notifications" :key="notification.id">
                    <button
                        type="button"
                        @click="openNotification(notification)"
                        class="w-full text-left px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition flex gap-3"
                        :class="notification.read_at ? 'bg-white' : 'bg-blue-50/50'"
                    >
                        <div class="h-9 w-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                            <i class="fas" :class="notification.icon || 'fa-bell'"></i>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-gray-900 truncate" x-text="notification.title"></p>

                                <span
                                    x-show="!notification.read_at"
                                    class="h-2 w-2 rounded-full bg-blue-600 shrink-0"
                                ></span>
                            </div>

                            <p class="mt-1 text-xs text-gray-600 line-clamp-2" x-text="notification.message"></p>

                            <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-400">
                                <span x-text="notification.module"></span>
                                <span>•</span>
                                <span x-text="notification.created_at || 'Just now'"></span>

                                <template x-if="notification.button_label">
                                    <span>•</span>
                                </template>

                                <template x-if="notification.button_label">
                                    <span class="text-blue-600 font-semibold" x-text="notification.button_label"></span>
                                </template>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <script>
        function notificationBell({ userId, initialUnreadCount, initialNotifications }) {
            return {
                open: false,
                unreadCount: initialUnreadCount || 0,
                notifications: initialNotifications || [],
                echoAttempts: 0,
                echoMaxAttempts: 30,
                echoConnected: false,
                syncing: false,

                init() {
                    this.connectEcho();

                    setTimeout(() => {
                        this.fetchUnreadNotifications();
                    }, 500);
                },

                connectEcho() {
                    if (!userId) {
                        console.warn('Notification bell: no authenticated user ID.');
                        return;
                    }

                    if (!window.Echo) {
                        this.echoAttempts++;

                        if (this.echoAttempts <= this.echoMaxAttempts) {
                            setTimeout(() => this.connectEcho(), 500);
                        } else {
                            console.warn('Notification bell: Echo is not available. Notifications will appear after refresh only.');
                        }

                        return;
                    }

                    if (this.echoConnected) {
                        return;
                    }

                    try {
                        const channelName = `App.Models.User.${userId}`;
                        const channel = window.Echo.private(channelName);

                        const syncAfterRealtime = (notification) => {
                            console.log('Notification bell: realtime event received', notification);

                            setTimeout(() => {
                                this.fetchUnreadNotifications();
                            }, 300);

                            setTimeout(() => {
                                this.fetchUnreadNotifications();
                            }, 1000);
                        };

                        channel.notification(syncAfterRealtime);

                        channel.listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', syncAfterRealtime);
                        channel.listen('Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', syncAfterRealtime);
                        channel.listen('.system.notification', syncAfterRealtime);
                        channel.listen('system.notification', syncAfterRealtime);
                        channel.listen('.BroadcastNotificationCreated', syncAfterRealtime);
                        channel.listen('BroadcastNotificationCreated', syncAfterRealtime);

                        channel.subscribed(() => {
                            console.log('Notification bell: subscribed to private user channel', userId);
                        });

                        channel.error((error) => {
                            console.error('Notification bell: private channel error', error);
                        });

                        this.echoConnected = true;
                        console.log('Notification bell: realtime connected for user', userId);
                    } catch (error) {
                        console.error('Notification bell: Echo connection failed.', error);
                    }
                },

                async fetchUnreadNotifications() {
                    if (this.syncing) {
                        return;
                    }

                    this.syncing = true;

                    try {
                        const response = await fetch('/notifications/unread', {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const data = await response.json();

                        this.notifications = data.notifications || [];
                        this.unreadCount = data.unread_count || 0;
                    } catch (error) {
                        console.error('Notification bell: fetch unread error.', error);
                    } finally {
                        this.syncing = false;
                    }
                },

                async openNotification(notification) {
                    if (!notification.read_at && notification.id) {
                        await this.markAsRead(notification);
                    }

                    if (notification.url && notification.url !== '#') {
                        window.location.href = notification.url;
                    }
                },

                async markAsRead(notification) {
                    try {
                        const response = await fetch(`/notifications/${notification.id}/read`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            console.error('Notification bell: failed to mark as read.', response.status);
                            return;
                        }

                        const data = await response.json();

                        this.notifications = this.notifications.filter((item) => item.id !== notification.id);
                        this.unreadCount = data.unread_count ?? Math.max(0, this.unreadCount - 1);

                        await this.fetchUnreadNotifications();
                    } catch (error) {
                        console.error('Notification bell: markAsRead error.', error);
                    }
                },

                async markAllAsRead() {
                    try {
                        const response = await fetch('/notifications/read-all', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            console.error('Notification bell: failed to mark all as read.', response.status);
                            return;
                        }

                        this.notifications = [];
                        this.unreadCount = 0;
                    } catch (error) {
                        console.error('Notification bell: markAllAsRead error.', error);
                    }
                },
            };
        }
    </script>
@endauth