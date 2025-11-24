const nodemailer = require('nodemailer');

const requiredEnv = ['IONOS_USER', 'IONOS_PASS', 'IONOS_FROM', 'IONOS_TO'];

const validateBody = (body = {}) => {
  const errors = [];
  const name = (body.name || '').trim();
  const email = (body.email || '').trim();
  const message = (body.message || '').trim();
  const quizAnswer = (body.quiz_answer || '').trim();
  const website = (body.website || '').trim();
  const startedAt = Number(body.form_started_at || 0);
  const now = Date.now();
  const minimumFormTimeMs = 3000;

  if (!name) errors.push('Name fehlt.');
  if (!email) errors.push('E-Mail fehlt.');
  if (!message) errors.push('Nachricht fehlt.');
  if (website) errors.push('Spam erkannt.');
  if (quizAnswer !== '5') errors.push('Kontrollfrage falsch.');
  if (Number.isFinite(startedAt) && now - startedAt < minimumFormTimeMs) {
    errors.push('Bitte Formular regulär ausfüllen.');
  }

  return { errors, name, email, message, startedAt };
};

module.exports = async (req, res) => {
  if (req.method !== 'POST') {
    res.statusCode = 405;
    res.setHeader('Allow', 'POST');
    res.json({ ok: false, error: 'POST erforderlich' });
    return;
  }

  let body = req.body;
  if (!body || typeof body === 'string') {
    try {
      body = JSON.parse(body || '{}');
    } catch (error) {
      res.statusCode = 400;
      res.json({ ok: false, error: 'Ungültiger JSON-Body' });
      return;
    }
  }

  const { errors, name, email, message } = validateBody(body);
  if (errors.length > 0) {
    res.statusCode = 400;
    res.json({ ok: false, error: errors.join(' ') });
    return;
  }

  const missing = requiredEnv.filter((key) => !process.env[key]);
  if (missing.length > 0) {
    res.statusCode = 500;
    res.json({ ok: false, error: `Server-Konfiguration fehlt (${missing.join(', ')})` });
    return;
  }

  const transporter = nodemailer.createTransport({
    host: 'smtp.ionos.de',
    port: 587,
    secure: false,
    auth: {
      user: process.env.IONOS_USER,
      pass: process.env.IONOS_PASS,
    },
  });

  const mailOptions = {
    from: process.env.IONOS_FROM,
    to: process.env.IONOS_TO,
    subject: 'Neue Kontaktaufnahme Homepage',
    replyTo: email,
    text: `Kontaktformular\n\nName: ${name}\nE-Mail: ${email}\n\nNachricht:\n${message}`,
  };

  try {
    await transporter.sendMail(mailOptions);
    res.statusCode = 200;
    res.json({ ok: true });
  } catch (error) {
    console.error('Fehler beim Mailversand:', error);
    res.statusCode = 500;
    res.json({ ok: false, error: 'E-Mail konnte nicht gesendet werden.' });
  }
};
