// Original sample questions for the starter packs that ship inside the app, so it works with no internet
// before anything is downloaded. They are deliberately simple and are NOT past-exam questions.
// Ids are negative so they can never clash with real questions and are never uploaded to the server.

const q = (text, options, answer, en, pcm) => ({ text, options, answer, en, pcm });

export const STARTER = [
  {
    exam: { slug: 'jamb', name: 'JAMB' },
    subject: { slug: 'english-language', name: 'English Language', display_name: 'Use of English' },
    items: [
      q('Choose the word that is nearest in meaning to <b>rapid</b>.', ['slow', 'quick', 'late', 'weak'], 'B', 'Rapid means fast. Quick is the closest word.', 'Rapid mean fast. Na quick dey closest.'),
      q('She ____ to school every day.', ['go', 'goes', 'going', 'gone'], 'B', 'With "she" we add -es to the verb in the present tense: she goes.', 'Wen na "she", we add -es to the verb: she goes.'),
      q('What is the plural of <i>child</i>?', ['childs', 'childes', 'children', 'childrens'], 'C', '"Child" has an irregular plural: children.', '"Child" no follow normal rule. The plural na children.'),
      q('Choose the word that is opposite in meaning to <b>generous</b>.', ['kind', 'mean', 'rich', 'happy'], 'B', 'A generous person gives freely. A mean person does not.', 'Generous person dey give well well. Mean person no dey give.'),
      q('Choose the correct spelling.', ['recieve', 'receive', 'receve', 'recive'], 'B', 'Remember: "i before e, except after c". So: receive.', 'Remember: "i before e, except after c". So na receive.'),
      q('The boys ____ playing football.', ['is', 'am', 'are', 'be'], 'C', '"Boys" is plural, so we use "are".', '"Boys" plenty, so we use "are".'),
      { passage: 'Read the passage and answer the two questions that follow.<br><br>Ada woke up early, washed her face and went to the market to buy tomatoes. She returned home before noon.' },
      q('What did Ada go to the market to buy?', ['rice', 'tomatoes', 'yams', 'bread'], 'B', 'The passage says she went to buy tomatoes.', 'The passage talk say she go buy tomatoes.'),
      q('When did Ada return home?', ['at night', 'before noon', 'in the evening', 'after lunch'], 'B', 'The passage says she returned before noon.', 'The passage talk say she come back before noon.'),
      q('Choose the correct sentence.', ['He don\'t like rice.', 'He doesn\'t likes rice.', 'He doesn\'t like rice.', 'He not like rice.'], 'C', 'After "doesn\'t" the verb stays in its base form: like.', 'After "doesn\'t" the verb no change: like.'),
      q('Which word is closest in meaning to <b>honest</b>?', ['truthful', 'lazy', 'noisy', 'careless'], 'A', 'An honest person tells the truth.', 'Honest person dey talk true.'),
    ],
  },
  {
    exam: { slug: 'jamb', name: 'JAMB' },
    subject: { slug: 'mathematics', name: 'Mathematics', display_name: 'Mathematics' },
    items: [
      q('What is 12 × 5?', ['50', '60', '65', '72'], 'B', '12 × 5 = 60.', '12 times 5 na 60.'),
      q('What is 3/4 + 1/4?', ['1/2', '3/4', '1', '4/8'], 'C', 'The bottom numbers match, so add the tops: 4/4 = 1.', 'The bottom numbers dey same, so add the top: 4/4 = 1.'),
      q('What is 15% of 200?', ['15', '30', '20', '45'], 'B', '15% of 200 = 0.15 × 200 = 30.', '15% of 200 na 0.15 × 200 = 30.'),
      q('Solve 2x + 3 = 11.', ['3', '4', '5', '7'], 'B', 'Take away 3: 2x = 8. Divide by 2: x = 4.', 'Comot 3: 2x = 8. Divide by 2: x = 4.'),
      q('A rectangle is 8 cm long and 5 cm wide. What is its area?', ['13 cm²', '26 cm²', '40 cm²', '45 cm²'], 'C', 'Area = length × width = 8 × 5 = 40.', 'Area na length times width = 8 × 5 = 40.'),
      q('Which of these is a prime number?', ['15', '21', '13', '27'], 'C', '13 can only be divided by 1 and itself.', '13 only dey divide by 1 and itself.'),
      q('What is 5<sup>2</sup>?', ['10', '25', '7', '52'], 'B', '5<sup>2</sup> means 5 × 5 = 25.', '5<sup>2</sup> mean 5 × 5 = 25.'),
      q('What is the average of 4, 6, 8 and 10?', ['6', '7', '8', '28'], 'B', 'Add them: 28. Divide by 4: 7.', 'Add dem: 28. Divide by 4: 7.'),
      q('What is the value of \\( \\sqrt{81} \\)?', ['7', '8', '9', '18'], 'C', '9 × 9 = 81, so the square root of 81 is 9.', '9 × 9 = 81, so square root of 81 na 9.'),
      q('A pen costs ₦50. How much do 6 pens cost?', ['₦250', '₦300', '₦350', '₦56'], 'B', '6 × ₦50 = ₦300.', '6 × ₦50 = ₦300.'),
    ],
  },
  {
    exam: { slug: 'jamb', name: 'JAMB' },
    subject: { slug: 'physics', name: 'Physics', display_name: 'Physics' },
    items: [
      q('What is the SI unit of force?', ['joule', 'newton', 'watt', 'pascal'], 'B', 'Force is measured in newtons (N).', 'We dey measure force for newtons (N).'),
      q('Speed is calculated as', ['distance × time', 'distance ÷ time', 'time ÷ distance', 'mass ÷ time'], 'B', 'Speed = distance ÷ time.', 'Speed = distance divide time.'),
      q('What is the SI unit of energy?', ['joule', 'newton', 'hertz', 'ampere'], 'A', 'Energy is measured in joules (J).', 'We dey measure energy for joules (J).'),
      q('Which of these is a vector quantity?', ['mass', 'time', 'velocity', 'temperature'], 'C', 'Velocity has both size and direction.', 'Velocity get size and direction.'),
      q('Light travels fastest in', ['water', 'glass', 'air', 'a vacuum'], 'D', 'Light is fastest in a vacuum.', 'Light dey fastest for vacuum.'),
      q('At normal pressure, water boils at', ['50 °C', '75 °C', '100 °C', '150 °C'], 'C', 'Water boils at 100 °C.', 'Water dey boil at 100 °C.'),
      q('Which instrument measures electric current?', ['voltmeter', 'ammeter', 'barometer', 'thermometer'], 'B', 'An ammeter measures current.', 'Ammeter dey measure current.'),
      q('What is the unit of frequency?', ['hertz', 'metre', 'kilogram', 'volt'], 'A', 'Frequency is measured in hertz (Hz).', 'We dey measure frequency for hertz (Hz).'),
    ],
  },
  {
    exam: { slug: 'jamb', name: 'JAMB' },
    subject: { slug: 'chemistry', name: 'Chemistry', display_name: 'Chemistry' },
    items: [
      q('What is the chemical symbol for sodium?', ['S', 'So', 'Na', 'Sn'], 'C', 'Sodium comes from its Latin name natrium: Na.', 'Sodium symbol na Na, from the Latin name natrium.'),
      q('What is the formula of water?', ['H<sub>2</sub>O', 'CO<sub>2</sub>', 'O<sub>2</sub>', 'NaCl'], 'A', 'Two hydrogen atoms and one oxygen atom: H<sub>2</sub>O.', 'Two hydrogen and one oxygen: H<sub>2</sub>O.'),
      q('What is the pH of a neutral solution?', ['0', '5', '7', '14'], 'C', 'A neutral solution such as pure water has a pH of 7.', 'Neutral solution like pure water get pH of 7.'),
      q('Which gas is needed for burning?', ['nitrogen', 'oxygen', 'helium', 'argon'], 'B', 'Things burn when they react with oxygen.', 'Things dey burn wen dem join oxygen.'),
      q('What is the atomic number of carbon?', ['4', '6', '8', '12'], 'B', 'Carbon has 6 protons, so its atomic number is 6.', 'Carbon get 6 protons, so atomic number na 6.'),
      q('Which of these is a noble gas?', ['oxygen', 'chlorine', 'argon', 'hydrogen'], 'C', 'Argon is in Group 18, the noble gases.', 'Argon dey Group 18, the noble gases.'),
      q('Iron rusts when it is in contact with', ['dry air only', 'oxygen and water', 'nitrogen only', 'oil'], 'B', 'Rusting needs both oxygen and water.', 'Rust need both oxygen and water.'),
      q('What is the formula of common salt?', ['NaOH', 'KCl', 'NaCl', 'HCl'], 'C', 'Common salt is sodium chloride: NaCl.', 'Common salt na sodium chloride: NaCl.'),
    ],
  },
];
