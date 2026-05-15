<?php

return [

    'chat-gpt' => [
        'apiKey' => getenv('OPENAI_API_KEY'),
    ],

    'gemini' => [
        'apiKey' => getenv('GEMINI_API_KEY'),
    ],

    'prompts' => [
        'footbal-match-predictions' => "
            Create a short football match analysis in a numbered list format with 6–8 points explaining why one team is
            likely to win. Include realistic reasons such as recent match results,
            home stadium advantage, player form, weather conditions, injuries, team chemistry, fan support, tactics,
            or bookmaker odds. Write it in a confident sports analyst style, short and clear, similar to betting predictions.

           -----

           Teams: :::teams:::
           EventDate: :::event_date:::
           Bookmakers_prediction: :::bookmakers_prediction:::
        "
    ],
];
