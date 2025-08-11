// Fungsi untuk membuka WhatsApp
function openWhatsApp() {
    const phoneNumber = "6281275037915"; 
    const message = "Halo, saya ingin menghubungi Pengadilan Tinggi Padang mengenai layanan Ruang Tamu Virtual.";
    const encodedMessage = encodeURIComponent(message);
    const whatsappURL = `https://wa.me/${phoneNumber}?text=${encodedMessage}`;
    window.open(whatsappURL, '_blank');
}

// Validasi form sebelum submit
document.getElementById('appointmentForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const nama = document.getElementById('nama').value.trim();
    const jabatan = document.getElementById('jabatan').value.trim();
    const nip = document.getElementById('nip').value.trim();
    const bertamuDengan = document.getElementById('bertamu_dengan').value;
    const tanggal = document.getElementById('tanggal').value;
    const nomorHp = document.getElementById('nomor_hp').value.trim();
    const keperluan = document.getElementById('keperluan').value.trim();

    if (!nama || !jabatan || !nip || !bertamuDengan || !tanggal || !nomorHp || !keperluan) {
        alert('Semua field harus diisi!');
        return false;
    }

    const phoneRegex = /^(\+62|62|0)[0-9]{8,13}$/;
    if (!phoneRegex.test(nomorHp)) {
        alert('Format nomor HP tidak valid! Contoh: 08123456789');
        return false;
    }

    const selectedDate = new Date(tanggal);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (selectedDate <= today) {
        alert('Tanggal kunjungan harus minimal H+1 dari hari ini!');
        return false;
    }

    if (confirm('Apakah data yang Anda masukkan sudah benar?')) {
        submitFormAndNotifyAdmin();
    }
});

// Fungsi submit form dan kirim notifikasi ke admin
function submitFormAndNotifyAdmin() {
    const form = document.getElementById('appointmentForm');
    const formData = new FormData(form);

    const submitBtn = document.querySelector('.submit-btn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
    submitBtn.disabled = true;

    fetch('process_appointment.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Permohonan berhasil dikirim! Pengadilan Tinggi Padang akan segera menghubungi Anda.');
                sendNotificationToAdmin(data.data);
                form.reset();
            } else {
                alert('Terjadi kesalahan: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengirim data. Silakan coba lagi.');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
}

// Fungsi untuk mengirim notifikasi ke admin Pengadilan Tinggi
function sendNotificationToAdmin(data) {
    const adminPhoneNumber = "6281234567890"; 

    const notificationMessage = `🔔 PERMOHONAN TAMU VIRTUAL BARU

📋 DETAIL PERMOHONAN:
👤 Nama: ${data.nama}
💼 Jabatan: ${data.jabatan}
🆔 NIP: ${data.nip}
👨‍⚖️ Bertamu dengan: ${data.pejabat_tujuan}
📅 Tanggal: ${data.tanggal_kunjungan}
📱 No HP: ${data.nomor_hp}
📝 Keperluan: ${data.keperluan}

⚠️ ID Permohonan: #${data.permohonan_id}

Silakan login ke sistem admin untuk memproses permohonan ini.
Link Admin: ${window.location.origin}/admin_dashboard.php`;

    const encodedMessage = encodeURIComponent(notificationMessage);
    const whatsappURL = `https://wa.me/${adminPhoneNumber}?text=${encodedMessage}`;

    setTimeout(() => {
        window.open(whatsappURL, '_blank');
    }, 2000);
}

// Fungsi untuk generate pesan WhatsApp otomatis
function generateWhatsAppMessage(formData) {
    const nama = formData.get('nama');
    const tanggal = formData.get('tanggal');
    const keperluan = formData.get('keperluan');

    const dateObj = new Date(tanggal);
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    const formattedDate = `${days[dateObj.getDay()]}, ${dateObj.getDate().toString().padStart(2, '0')} ${months[dateObj.getMonth()]} ${dateObj.getFullYear()}`;

    const autoMessage = `Hi Ini adalah pesan otomatis Aplikasi Sistem Pembinaan Tenaga Teknis (SIGANIS),

Berikut adalah detil informasi untuk anda:

Kepada Yth:
${nama}

Layanan
Ruang Tamu Virtual Ditbinganis

Permohonan tamu virtual a.n. ${nama} telah diterima Aplikasi SIGANIS.

Hari/Tanggal : ${formattedDate}
Waktu : 09:30 - 09:45 WIB  
Perihal : ${keperluan}

Penamaan Akun Zoom Saat Pertemuan Wajib : ${nama}

Jika penamaan akun zoom saat pertemuan tidak sesuai di atas maka tidak akan diterima

Permohonan anda akan diproses dan selanjutnya diinformasikan via WhatsApp pada nomor yang terdaftar pada Aplikasi SIKEP Mahkamah Agung RI`;

    const phoneNumber = "6281234567890"; 
    const encodedMessage = encodeURIComponent(autoMessage);
    const whatsappURL = `https://wa.me/${phoneNumber}?text=${encodedMessage}`;

    setTimeout(() => {
        window.open(whatsappURL, '_blank');
    }, 2000);
}

// Set minimum date untuk input tanggal (H+1)
document.addEventListener('DOMContentLoaded', function () {
    const tanggalInput = document.getElementById('tanggal');
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);

    const year = tomorrow.getFullYear();
    const month = (tomorrow.getMonth() + 1).toString().padStart(2, '0');
    const day = tomorrow.getDate().toString().padStart(2, '0');

    tanggalInput.min = `${year}-${month}-${day}`;
});

// Format nomor HP otomatis
document.getElementById('nomor_hp').addEventListener('input', function (e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.startsWith('0')) {
        value = '62' + value.substring(1);
    }
    if (!value.startsWith('62')) {
        value = '62' + value;
    }
    e.target.value = value;
});

// Validasi NIP (hanya angka)
document.getElementById('nip').addEventListener('input', function (e) {
    e.target.value = e.target.value.replace(/\D/g, '');
});

// Smooth scroll untuk form elements
document.querySelectorAll('input, select, textarea').forEach(element => {
    element.addEventListener('focus', function () {
        this.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
});
