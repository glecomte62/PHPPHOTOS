INSERT IGNORE INTO pages (slug, title, `lead`, body_html, pilot_info) VALUES (
  'presentation',
  'Présentation',
  'Photographe amateur et pilote ULM, curieux de tout ce qui se voit d''en haut.',
  '<p>Texte de présentation à rédiger depuis l''administration (Contenus → Page Présentation).</p>',
  'ULM : à compléter\nTerrain de base : à compléter\nHeures de vol : à compléter'
);

INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('hero_photo_id', NULL),
  ('site_tagline', 'Photographe & pilote ULM'),
  ('sky_intro', 'Depuis le cockpit, les paysages se dévoilent autrement : des lignes, des reliefs, des lumières rasantes. Une sélection de mes vues du ciel.');
