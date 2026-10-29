<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>John Kelly &amp; Company - Log in</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fadeIn { animation: fadeIn 0.5s ease-out forwards; }

        :root {
            --navy: #102d79;
            --blue: #1d54e2;
            --black: #000000;
            --fog: #f1f2f2;
            --white: #ffffff;
        }

        body {
            font-family: "Space Grotesk", sans-serif;
            background-color: var(--fog);
            background-image:
                radial-gradient(1200px 300px at 10% -20%, rgba(29, 84, 226, 0.12), transparent 60%),
                radial-gradient(900px 260px at 90% 0%, rgba(16, 45, 121, 0.14), transparent 55%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.7), rgba(241, 242, 242, 0.9));
        }

        .brand-title {
            font-family: "Fraunces", serif;
            letter-spacing: 0.02em;
        }

        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }
    </style>
</head>

<body class="h-screen text-[#000000]">

<div class="grid grid-cols-1 md:grid-cols-2 min-h-screen">

    <div class="flex items-center justify-center p-8 bg-[#ffffff]">
        <div class="relative w-full max-w-md bg-[#ffffff] rounded-2xl shadow-[0_20px_50px_rgba(16,45,121,0.12)] p-12 animate-fadeIn border border-[#102d79]/10">
            <div class="flex justify-center mb-8">
                <img
                    src="{{ asset('images/imaglogo.png') }}"
                    alt="John Kelly & Company"
                    class="h-16 object-contain"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >

                <div class="hidden flex-col items-center justify-center text-center uppercase tracking-tighter">
                    <span class="text-xl font-black text-[#102d79] leading-none brand-title">John Kelly</span>
                    <span class="text-[#1d54e2] font-black text-lg my-1 brand-title">&</span>
                    <span class="text-xl font-black text-[#102d79] leading-none brand-title">Company</span>
                </div>
            </div>

            <h1 class="text-center text-2xl font-semibold text-[#102d79] mb-1 brand-title">
                Log in
            </h1>

            <p class="text-center text-[#102d79]/60 text-sm mb-6">
                Please log in using the account created by your system administrator.
            </p>

            @if($errors->any())
                <div class="bg-[#f1f2f2] border-l-4 border-[#1d54e2] text-[#102d79] text-center text-sm p-3 rounded mb-5 shadow-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            @if(session('success'))
                <div class="bg-[#f1f2f2] border-l-4 border-[#102d79] text-[#102d79] text-center text-sm p-3 rounded mb-5 shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-[#102d79] font-medium mb-1">
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        class="w-full px-4 py-3 border rounded-lg border-[#102d79]/20 focus:outline-none focus:ring-2 focus:ring-[#1d54e2] focus:border-[#1d54e2] bg-[#f1f2f2]"
                    >
                </div>

                <div>
                    <label for="password" class="block text-[#102d79] font-medium mb-1">
                        Password
                    </label>

                    <div class="relative">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            required
                            autocomplete="current-password"
                            class="w-full px-4 py-3 pr-20 border rounded-lg border-[#102d79]/20 focus:outline-none focus:ring-2 focus:ring-[#1d54e2] focus:border-[#1d54e2] bg-[#f1f2f2]"
                        >

                        <button
                            type="button"
                            onclick="togglePassword(this)"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-[#102d79]/70 text-sm font-semibold hover:text-[#102d79]"
                        >
                            Show
                        </button>
                    </div>

                    <div class="flex justify-end mt-2">
                        <a href="{{ route('password.assistance') }}" class="text-[#1d54e2] text-xs font-semibold hover:text-[#102d79]">
                            Need help accessing your account?
                        </a>
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full py-3 bg-[#1d54e2] hover:bg-[#102d79] text-white font-semibold rounded-full shadow-md transition duration-200"
                >
                    Log in
                </button>
            </form>

        </div>
    </div>

    <div class="relative hidden md:block overflow-hidden bg-[#102d79]">
        <img
            src="{{ asset('images/imag1logo.jpg') }}"
            alt="Hero Image"
            class="w-full h-full object-cover"
            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
        >

        <div class="hidden flex-col items-center justify-center w-full h-full bg-gradient-to-br from-[#102d79] via-[#1d54e2] to-[#102d79] text-white text-center p-12">
            <h2 class="text-3xl font-bold mb-4 brand-title">
                Precision Management
            </h2>
            <p class="text-white/80 italic">
                John Kelly & Company Enterprise System
            </p>
        </div>

        <div class="absolute inset-0 bg-[#102d79]/20 mix-blend-multiply pointer-events-none"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(255,255,255,0.18),_transparent_55%)]"></div>
    </div>

</div>

<script>
    function togglePassword(button) {
        const input = document.getElementById('password');

        if (!input) {
            return;
        }

        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        button.textContent = isHidden ? 'Hide' : 'Show';
    }

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>

</body>
</html>
