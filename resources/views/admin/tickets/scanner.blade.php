@extends('layouts.admin')

@section('title', 'Ticket Scanner')
@section('page-title', 'QR Code Scanner')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Scanner Section -->
            <div>
                <h2 class="text-xl font-semibold mb-4">Scan QR Code</h2>
                <div id="scanner-container" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center mb-4" style="min-height: 300px;">
                    <video id="video" autoplay playsinline style="width: 100%; max-width: 400px; display: none;"></video>
                    <canvas id="canvas" style="display: none;"></canvas>
                    <div id="scanner-placeholder">
                        <i class="fas fa-qrcode text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 mb-4">Click to start camera</p>
                        <button onclick="startScanner()" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90">
                            <i class="fas fa-camera mr-2"></i> Start Scanner
                        </button>
                    </div>
                </div>
                
                <!-- Manual Entry -->
                <div class="border-t pt-4">
                    <h3 class="font-semibold mb-2">Or Enter Ticket Number</h3>
                    <div class="flex gap-2">
                        <input type="text" id="manual-ticket-number" placeholder="TKT-20260127-ABC123-001" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
                        <button onclick="manualCheckIn()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                            Check In
                        </button>
                    </div>
                </div>
            </div>

            <!-- Result Section -->
            <div>
                <h2 class="text-xl font-semibold mb-4">Ticket Information</h2>
                <div id="result-container" class="border border-gray-200 rounded-lg p-6 min-h-[300px]">
                    <div class="text-center text-gray-400 py-12">
                        <i class="fas fa-ticket-alt text-4xl mb-4"></i>
                        <p>Scan a QR code or enter ticket number</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
    let videoStream = null;
    let scanning = false;

    function startScanner() {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(stream => {
                videoStream = stream;
                const video = document.getElementById('video');
                const canvas = document.getElementById('canvas');
                const ctx = canvas.getContext('2d');
                
                video.srcObject = stream;
                video.style.display = 'block';
                document.getElementById('scanner-placeholder').style.display = 'none';
                
                video.onloadedmetadata = () => {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    scanning = true;
                    scanQR();
                };
            })
            .catch(err => {
                alert('Camera access denied. Please allow camera access.');
                console.error(err);
            });
    }

    function scanQR() {
        if (!scanning) return;
        
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');
        
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height);
        
        if (code) {
            verifyTicket(code.data);
        } else {
            requestAnimationFrame(scanQR);
        }
    }

    function verifyTicket(qrData) {
        fetch('{{ route("admin.tickets.scanner.verify") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ qr_data: qrData })
        })
        .then(res => res.json())
        .then(data => {
            if (data.valid) {
                displayTicketInfo(data.ticket);
            } else {
                displayError(data.message);
            }
        })
        .catch(err => {
            displayError('Error verifying ticket');
            console.error(err);
        });
    }

    function displayTicketInfo(ticket) {
        const container = document.getElementById('result-container');
        container.innerHTML = `
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                <div class="flex items-center text-green-800">
                    <i class="fas fa-check-circle text-2xl mr-3"></i>
                    <span class="font-semibold">Valid Ticket</span>
                </div>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="text-sm text-gray-500">Ticket Number</label>
                    <p class="font-semibold">${ticket.ticket_number}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Customer</label>
                    <p class="font-semibold">${ticket.customer_name}</p>
                    <p class="text-sm text-gray-600">${ticket.customer_email}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Event</label>
                    <p class="font-semibold">${ticket.event_title}</p>
                    <p class="text-sm text-gray-600">${ticket.venue}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Ticket Type</label>
                    <p class="font-semibold">${ticket.ticket_type}</p>
                </div>
                <button onclick="checkInTicket(${ticket.id})" class="w-full bg-primary text-white py-2 rounded-lg hover:bg-primary/90 mt-4">
                    <i class="fas fa-check mr-2"></i> Check In
                </button>
            </div>
        `;
    }

    function displayError(message) {
        const container = document.getElementById('result-container');
        container.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center text-red-800">
                    <i class="fas fa-times-circle text-2xl mr-3"></i>
                    <span class="font-semibold">${message}</span>
                </div>
            </div>
        `;
    }

    function checkInTicket(ticketId) {
        fetch('{{ route("admin.tickets.scanner.check-in") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ ticket_id: ticketId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Ticket checked in successfully!');
                document.getElementById('result-container').innerHTML = '<div class="text-center text-green-600 py-8"><i class="fas fa-check-circle text-4xl mb-2"></i><p>Checked In</p></div>';
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function manualCheckIn() {
        const ticketNumber = document.getElementById('manual-ticket-number').value;
        if (!ticketNumber) {
            alert('Please enter ticket number');
            return;
        }

        fetch('{{ route("admin.tickets.scanner.manual-check-in") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ ticket_number: ticketNumber })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Ticket checked in successfully!');
                document.getElementById('manual-ticket-number').value = '';
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    // Stop scanner when leaving page
    window.addEventListener('beforeunload', () => {
        if (videoStream) {
            videoStream.getTracks().forEach(track => track.stop());
        }
    });
</script>
@endsection
