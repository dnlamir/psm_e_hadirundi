function sendReminders(roomId) {
    fetch('send_reminders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'room_id=' + roomId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Open WhatsApp for each voter with a small delay
            data.links.forEach((voter, index) => {
                setTimeout(() => {
                    window.open(voter.url, '_blank');
                }, index * 1000); // 1 second delay between each window
            });
            alert(`Peringatan telah dihantar kepada ${data.count} pengundi yang belum hadir.`);
        } else {
            alert(data.error);
        }
    });
}