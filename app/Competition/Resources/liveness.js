(function () {
    'use strict';

    var nativeFetch = window.fetch.bind(window);
    var storePath = '/presensi/face/store';
    var challengePath = '/competition/liveness/challenge';

    function showInstruction(instruction) {
        var panel = document.getElementById('competition-liveness-panel');
        if (!panel) {
            panel = document.createElement('div');
            panel.id = 'competition-liveness-panel';
            panel.setAttribute('role', 'status');
            panel.style.cssText = 'position:fixed;z-index:20000;left:50%;top:16px;transform:translateX(-50%);max-width:92vw;padding:14px 20px;background:#102a43;color:#fff;border-radius:8px;box-shadow:0 4px 18px #0005;font:600 16px sans-serif;text-align:center';
            document.body.appendChild(panel);
        }

        var text = {
            blink: 'Untuk verifikasi, kedipkan mata sekali.',
            turn_left: 'Untuk verifikasi, tengok ke kiri layar.',
            turn_right: 'Untuk verifikasi, tengok ke kanan layar.'
        };
        panel.textContent = text[instruction] || 'Ikuti instruksi verifikasi.';
        return panel;
    }

    function distance(a, b) {
        return Math.hypot(a.x - b.x, a.y - b.y);
    }

    function eyeAspectRatio(points) {
        if (!points || points.length < 6) return null;
        var width = distance(points[0], points[3]);
        if (width <= 0) return null;
        return (distance(points[1], points[5]) + distance(points[2], points[4])) / (2 * width);
    }

    function faceMetrics(video) {
        return faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks()
            .then(function (result) {
                if (!result) return null;
                var eyesLeft = result.landmarks.getLeftEye();
                var eyesRight = result.landmarks.getRightEye();
                var nose = result.landmarks.getNose();
                var eyeCenters = [
                    eyesLeft.reduce(function (sum, point) { return sum + point.x; }, 0) / eyesLeft.length,
                    eyesRight.reduce(function (sum, point) { return sum + point.x; }, 0) / eyesRight.length
                ].sort(function (a, b) { return a - b; });
                var noseX = nose.reduce(function (sum, point) { return sum + point.x; }, 0) / nose.length;
                var eyeWidth = eyeCenters[1] - eyeCenters[0];
                if (eyeWidth <= 0) return null;
                return {
                    ear: (eyeAspectRatio(eyesLeft) + eyeAspectRatio(eyesRight)) / 2,
                    yaw: (noseX - eyeCenters[0]) / eyeWidth
                };
            });
    }

    function waitForModels() {
        return new Promise(function (resolve, reject) {
            var started = Date.now();
            var timer = window.setInterval(function () {
                if (window.faceapi && faceapi.nets.tinyFaceDetector.isLoaded && faceapi.nets.faceLandmark68Net.isLoaded) {
                    window.clearInterval(timer);
                    resolve();
                } else if (Date.now() - started > 8000) {
                    window.clearInterval(timer);
                    reject(new Error('Model liveness belum siap.'));
                }
            }, 100);
        });
    }

    async function runChallenge(challenge) {
        var panel = showInstruction(challenge.instruction);
        try {
            await waitForModels();
            var video = document.getElementById('videoEl');
            if (!video || video.readyState < 2) throw new Error('Kamera belum siap.');

            var threshold = challenge.proof_thresholds || {};
            var readings = [];
            var started = Date.now();
            var deadline = started + 8000;
            while (Date.now() < deadline) {
                var reading = await faceMetrics(video);
                if (reading) readings.push(reading);
                if (readings.length >= 5) {
                    var baseline = readings.slice(0, 3).reduce(function (sum, item) { return sum + item.yaw; }, 0) / 3;
                    var delta;
                    if (challenge.instruction === 'blink') {
                        var ears = readings.map(function (item) { return item.ear; });
                        delta = Math.max.apply(null, ears) - Math.min.apply(null, ears);
                        if (delta >= Number(threshold.blink_delta_min || 0.10)) {
                            return { status: 'passed', evidence: { delta: delta, elapsed_ms: Date.now() - started } };
                        }
                    } else {
                        var yaw = readings[readings.length - 1].yaw;
                        delta = yaw - baseline;
                        var turnThreshold = Number(threshold.turn_delta_min || 0.12);
                        if ((challenge.instruction === 'turn_left' && delta <= -turnThreshold)
                            || (challenge.instruction === 'turn_right' && delta >= turnThreshold)) {
                            return { status: 'passed', evidence: { delta: delta, elapsed_ms: Date.now() - started } };
                        }
                    }
                }
                await new Promise(function (resolve) { window.setTimeout(resolve, 220); });
            }

            return { status: 'failed', evidence: { delta: 0, elapsed_ms: Date.now() - started } };
        } catch (error) {
            return { status: 'unavailable', evidence: {} };
        } finally {
            window.setTimeout(function () { panel.remove(); }, 1800);
        }
    }

    window.fetch = async function (input, init) {
        var url;
        try {
            url = new URL(input instanceof Request ? input.url : input, window.location.href);
        } catch (error) {
            return nativeFetch(input, init);
        }
        if (url.pathname !== storePath || url.origin !== window.location.origin) {
            return nativeFetch(input, init);
        }

        var options = init || {};
        var payload;
        try {
            payload = JSON.parse(options.body);
        } catch (error) {
            payload = {};
        }

        try {
            var csrf = document.querySelector('meta[name="csrf-token"]');
            var challengeResponse = await nativeFetch(challengePath, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf ? csrf.content : ''
                },
                credentials: 'same-origin'
            });
            if (!challengeResponse.ok) {
                payload.liveness_status = 'unavailable';
                return nativeFetch(input, Object.assign({}, options, { body: JSON.stringify(payload) }));
            }

            var challenge = await challengeResponse.json();
            var result = await runChallenge(challenge);
            payload.challenge_nonce = challenge.nonce;
            payload.liveness_status = result.status;
            payload.liveness_evidence = result.evidence;
        } catch (error) {
            payload.liveness_status = 'unavailable';
        }

        return nativeFetch(input, Object.assign({}, options, { body: JSON.stringify(payload) }));
    };
})();
