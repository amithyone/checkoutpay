<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Email Payment Gateway</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .fade-in {
            animation: fadeIn 0.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full">
        <div class="bg-white rounded-lg shadow-xl p-8 fade-in">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">🚀 Setup Wizard</h1>
                <p class="text-gray-600">Configure your Email Payment Gateway</p>
            </div>

            <!-- Step 1: Database Configuration -->
            <div id="step1" class="step">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Step 1: Database Configuration</h2>
                
                <form id="dbForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Database Host</label>
                        <input type="text" name="host" id="host" value="localhost" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Database Port</label>
                        <input type="number" name="port" id="port" value="3306" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Database Name</label>
                        <input type="text" name="database" id="database" required
                            placeholder="e.g., checzspw_checkout"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Database Username</label>
                        <input type="text" name="username" id="username" required
                            placeholder="e.g., checzspw_checkout"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Database Password</label>
                        <input type="password" name="password" id="password"
                            placeholder="Enter database password"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Test Result -->
                    <div id="testResult" class="hidden mt-4 p-4 rounded-lg"></div>

                    <div class="flex gap-3">
                        <button type="button" id="testBtn" 
                            class="flex-1 bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition font-medium">
                            🔍 Test Connection
                        </button>
                        <button type="submit" id="saveBtn" disabled
                            class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                            💾 Save & Continue
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 2: Setup Complete -->
            <div id="step2" class="step hidden">
                <div class="text-center">
                    <div class="text-6xl mb-4">✅</div>
                    <h2 class="text-2xl font-semibold text-gray-800 mb-4">Setup Complete!</h2>
                    <p class="text-gray-600 mb-6">Database configured successfully. Running migrations...</p>
                    <div id="setupProgress" class="space-y-2">
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <span class="loader">⏳</span>
                            <span>Running migrations...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const testBtn = document.getElementById('testBtn');
        const saveBtn = document.getElementById('saveBtn');
        const dbForm = document.getElementById('dbForm');
        const testResult = document.getElementById('testResult');
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const setupProgress = document.getElementById('setupProgress');

        let connectionTested = false;

        // Test database connection
        testBtn.addEventListener('click', async () => {
            const formData = new FormData(dbForm);
            const data = Object.fromEntries(formData);

            testBtn.disabled = true;
            testBtn.textContent = '🔄 Testing...';
            testResult.classList.add('hidden');

            try {
                const response = await fetch('/setup/test-database', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                
                testResult.classList.remove('hidden');
                
                if (result.success) {
                    testResult.className = 'mt-4 p-4 rounded-lg bg-green-100 border border-green-400 text-green-700';
                    testResult.innerHTML = '✅ <strong>Success!</strong> ' + result.message;
                    connectionTested = true;
                    saveBtn.disabled = false;
                } else {
                    testResult.className = 'mt-4 p-4 rounded-lg bg-red-100 border border-red-400 text-red-700';
                    testResult.innerHTML = '❌ <strong>Failed!</strong> ' + result.message;
                    connectionTested = false;
                    saveBtn.disabled = true;
                }
            } catch (error) {
                testResult.classList.remove('hidden');
                testResult.className = 'mt-4 p-4 rounded-lg bg-red-100 border border-red-400 text-red-700';
                testResult.innerHTML = '❌ <strong>Error!</strong> ' + error.message;
                connectionTested = false;
                saveBtn.disabled = true;
            }

            testBtn.disabled = false;
            testBtn.textContent = '🔍 Test Connection';
        });

        // Save database configuration
        dbForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!connectionTested) {
                alert('Please test the connection first!');
                return;
            }

            const formData = new FormData(dbForm);
            const data = Object.fromEntries(formData);

            saveBtn.disabled = true;
            saveBtn.textContent = '💾 Saving...';

            try {
                const response = await fetch('/setup/save-database', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    // Move to step 2
                    step1.classList.add('hidden');
                    step2.classList.remove('hidden');

                    // Complete setup
                    await completeSetup();
                } else {
                    alert('Failed to save: ' + result.message);
                    saveBtn.disabled = false;
                    saveBtn.textContent = '💾 Save & Continue';
                }
            } catch (error) {
                alert('Error: ' + error.message);
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Save & Continue';
            }
        });

        // Complete setup
        async function completeSetup() {
            setupProgress.innerHTML = `
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <span>⏳</span>
                    <span>Running migrations...</span>
                </div>
            `;

            try {
                const response = await fetch('/setup/complete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    setupProgress.innerHTML = `
                        <div class="flex items-center gap-2 text-sm text-green-600 mb-4">
                            <span>✅</span>
                            <span>Migrations completed!</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-green-600 mb-4">
                            <span>✅</span>
                            <span>Database seeded!</span>
                        </div>
                        <a href="${result.redirect}" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-medium">
                            Go to Admin Panel →
                        </a>
                    `;
                } else {
                    setupProgress.innerHTML = `
                        <div class="text-red-600 mb-4">❌ Setup failed: ${result.message}</div>
                    `;
                }
            } catch (error) {
                setupProgress.innerHTML = `
                    <div class="text-red-600 mb-4">❌ Error: ${error.message}</div>
                `;
            }
        }
    </script>
</body>
</html>
