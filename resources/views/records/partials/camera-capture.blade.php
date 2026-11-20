{{-- Camera capture. The UI mock has no reference for this control, so it follows the
     ORDO panel, button and border conventions and needs design review.

     Mounted on both dropzones: the dedicated intake form and the Unclassified
     Records queue. One partial so the two cannot drift apart.

     This is its own Alpine root: $refs, $nextTick and $root are only injected on
     a root x-data scope, so nesting it under a parent component's data would
     break them. It talks to whatever page holds it through two window events —
     ordo:camera-open to open, ordo:camera-photo to hand back the captured file. --}}
<div x-data="cameraCapture()"
     x-on:ordo:camera-open.window="openCapture()"
     x-on:keydown.escape.window="if (open) close()">
    <template x-if="open">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-[rgba(15,23,42,.28)] p-2.5"
         x-on:click.self="close()">
        <div class="w-full max-w-[560px] overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(15,23,42,.22)]"
             role="dialog" aria-modal="true" aria-labelledby="camera-capture-title">
            <div class="flex items-start justify-between border-b border-ordo-line px-4 py-3.5">
                <div>
                    <h3 id="camera-capture-title" class="m-0 text-[14px] font-bold">Capture Document</h3>
                    <p class="mt-1 mb-0 text-[10px] text-ordo-muted">
                        Photograph a document to add it to Unclassified Records.
                    </p>
                </div>
                <button type="button"
                        class="-mr-1 flex h-7 w-7 items-center justify-center rounded-lg border-0 bg-transparent text-[20px] leading-none text-ordo-muted transition-colors hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-2 focus-visible:ring-ordo-blue"
                        x-on:click="close()" aria-label="Close">&times;</button>
            </div>

            <div class="px-4 py-4">
                <div x-show="error" x-cloak
                     class="mb-3 rounded-lg border border-[#f4cccc] bg-[#fff8f8] px-3 py-2 text-[11px] text-[#b42318]">
                    <span x-text="error"></span>
                </div>

                <div class="relative overflow-hidden rounded-lg border border-[#d9dee7] bg-[#f8fafc]">
                    <video x-show="! shot && ! error" x-ref="video" playsinline muted
                           class="max-h-[380px] w-full bg-[#1f2937] object-contain"
                           x-bind:class="starting ? 'opacity-40' : 'opacity-100'"></video>

                    <img x-show="shot" x-cloak x-ref="shot" alt="Captured document"
                         class="max-h-[380px] w-full bg-white object-contain" />

                    <div x-show="! shot && ! error && ! stream" x-cloak
                         class="grid min-h-[220px] place-items-center p-6 text-center text-[11px] text-ordo-faint">
                        <span x-show="starting">Starting camera&hellip;</span>
                    </div>

                    <div x-show="! shot && ! error && stream" x-cloak
                         class="absolute inset-x-0 bottom-0 flex justify-center p-2">
                        <span class="rounded-full bg-[rgba(15,23,42,.62)] px-2.5 py-1 text-[10px] text-white">
                            Point the camera at the document, then choose Capture Photo.
                        </span>
                    </div>
                </div>

                <div x-show="devices.length > 1" x-cloak class="mt-3">
                    <x-ordo.field label="Camera">
                        <select x-on:change="switchCamera($event.target.value)"
                                class="w-full rounded-lg border border-ordo-line bg-white px-2.5 py-2 text-[12px] outline-none">
                            <template x-for="(device, i) in devices" x-bind:key="device.deviceId">
                                <option x-bind:value="device.deviceId"
                                        x-text="device.label || ('Camera ' + (i + 1))"></option>
                            </template>
                        </select>
                    </x-ordo.field>
                </div>

                <div x-show="stream" x-cloak class="mt-3 flex justify-center">
                    <x-ordo.button type="button" variant="default" x-on:click="switchCamera()">
                        Switch Camera
                    </x-ordo.button>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-ordo-line px-4 py-3">
                <template x-if="! shot">
                    <div class="flex gap-2">
                        <x-ordo.button type="button" variant="default" x-on:click="close()">Cancel</x-ordo.button>
                        <x-ordo.button type="button" variant="primary"
                                       x-bind:disabled="starting || error || ! supported || ! stream"
                                       x-on:click="capture()">Capture Photo</x-ordo.button>
                    </div>
                </template>

                <template x-if="shot">
                    <div class="flex gap-2">
                        <x-ordo.button type="button" variant="default" x-on:click="retake()">Retake</x-ordo.button>
                        <x-ordo.button type="button" variant="primary" x-on:click="usePhoto()">Use Photo</x-ordo.button>
                    </div>
                </template>
            </div>
        </div>
    </div>
    </template>
</div>