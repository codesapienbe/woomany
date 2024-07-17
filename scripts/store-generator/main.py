# Generate 1000 mock stores for additional European countries

# Function to generate realistic store names and URLs
import random

import numpy as np
import pandas as pd


def generate_store_name_url(city, country):
    base_names = ["Clean", "Shine", "Sparkle", "Fresh", "Bright", "Neat", "Tidy", "Polish", "Glow", "Gleam", "Glisten",
                    "Lustre", "Radiant", "Sheen", "Sleek", "Gloss", "Luminous", "Lustrous", "Lustrum", "Lustrate",
                    "Lustration", "Schoon", "Schön", "Schöne", "Schöner", "Schönes", "Schönheit", "Schönsten",
                    "Woning", "Woningen", "Woningbouw", "Woningcorporatie", "Woningcorporaties", "Woningmarkt",
                    "Woningnood", "Woningstichting", "Woningstichtingen", "Woningvoorraad", "Woningwet", "Woningwetgeving",
                    "Snelle", "Sneller", "Snelste", "Snelheid", "Snelweg", "Snelwegen", "Snelheidsbeperking",
                    "Schoonmaak", "Schoonmaakbedrijf", "Schoonmaakbedrijven", "Schoonmaakwerk", "Schoonmaakwerkzaamheden",
                    "Schoonmaakster", "Schoonmaaksters", "Schoonmaakmiddel", "Schoonmaakmiddelen", "Schoonmaakazijn",
                    "Schoonmaakazijnen", "Schoonmaakazijnfabriek", "Schoonmaakazijnfabrieken", "Schoonmaakazijnfles",
                    "Schoonmaakazijnflessen", "Schoonmaakazijngeur", "Schoonmaakazijngeuren", "Schoonmaakazijnkalk"
                ]
    name = f"{random.choice(base_names)} {city}"
    url = f"https://{name.replace(' ', '-').lower()}.poetsme.app"
    return name, url

# List of additional countries and sample cities
additional_countries_cities = {
    "Spain": ["Madrid", "Barcelona", "Valencia", "Seville", "Zaragoza"],
    "Italy": ["Rome", "Milan", "Naples", "Turin", "Palermo"],
    "Sweden": ["Stockholm", "Gothenburg", "Malmö", "Uppsala", "Västerås"],
    "Poland": ["Warsaw", "Krakow", "Łódź", "Wrocław", "Poznań"],
    "Austria": ["Vienna", "Graz", "Linz", "Salzburg", "Innsbruck"],
    "Norway": ["Oslo", "Bergen", "Stavanger", "Trondheim", "Drammen"],
    "Belgium": ["Brussels", "Antwerp", "Ghent", "Charleroi", "Liège"],
    "Denmark": ["Copenhagen", "Aarhus", "Odense", "Aalborg", "Esbjerg"],
    "Finland": ["Helsinki", "Espoo", "Tampere", "Vantaa", "Oulu"],
    "Ireland": ["Dublin", "Cork", "Limerick", "Galway", "Waterford"],
    "Portugal": ["Lisbon", "Porto", "Vila Nova de Gaia", "Amadora", "Braga"],
    "Greece": ["Athens", "Thessaloniki", "Patras", "Heraklion", "Larissa"],
    "Czech Republic": ["Prague", "Brno", "Ostrava", "Plzeň", "Liberec"],
    "Hungary": ["Budapest", "Debrecen", "Szeged", "Miskolc", "Pécs"],
    "Slovakia": ["Bratislava", "Košice", "Prešov", "Žilina", "Nitra"],
    "Croatia": ["Zagreb", "Split", "Rijeka", "Osijek", "Zadar"],
    "Bulgaria": ["Sofia", "Plovdiv", "Varna", "Burgas", "Ruse"],
    "Romania": ["Bucharest", "Cluj-Napoca", "Timișoara", "Iași", "Constanța"],
    "Lithuania": ["Vilnius", "Kaunas", "Klaipėda", "Šiauliai", "Panevėžys"],
    "Latvia": ["Riga", "Daugavpils", "Liepāja", "Jelgava", "Jūrmala"],
    "Estonia": ["Tallinn", "Tartu", "Narva", "Pärnu", "Kohtla-Järve"],
    "Slovenia": ["Ljubljana", "Maribor", "Celje", "Kranj", "Koper"],
    "Cyprus": ["Nicosia", "Limassol", "Larnaca", "Famagusta", "Paphos"],
    "Malta": ["Valletta", "Birkirkara", "Mosta", "Qormi", "Żabbar"],
    "Luxembourg": ["Luxembourg City", "Esch-sur-Alzette", "Differdange", "Dudelange", "Ettelbruck"],
    "Iceland": ["Reykjavík", "Kópavogur", "Hafnarfjörður", "Akureyri", "Reykjanesbær"],
    "Liechtenstein": ["Vaduz", "Schaan", "Triesen", "Balzers", "Eschen"],
    "Monaco": ["Monaco", "Monte Carlo", "La Condamine", "Fontvieille", "Les Moneghetti"],
    "Andorra": ["Andorra la Vella", "Escaldes-Engordany", "Encamp", "Sant Julià de Lòria", "La Massana"],
    "San Marino": ["San Marino", "Serravalle", "Borgo Maggiore", "Domagnano", "Fiorentino"],
    "Vatican City": ["Vatican City", "Vatican Gardens", "Vatican Museums", "St. Peter's Basilica", "Sistine Chapel"],
    "Moldova": ["Chișinău", "Tiraspol", "Bălți", "Bender", "Rîbnița"],
    "Ukraine": ["Kyiv", "Kharkiv", "Odesa", "Dnipro", "Donetsk"],
    "Belarus": ["Minsk", "Gomel", "Mogilev", "Vitebsk", "Hrodna"],
    "Moldova": ["Chișinău", "Tiraspol", "Bălți", "Bender", "Rîbnița"],
    "Ukraine": ["Kyiv", "Kharkiv", "Odesa", "Dnipro", "Donetsk"],
    "Belarus": ["Minsk", "Gomel", "Mogilev", "Vitebsk", "Hrodna"],
    "Serbia": ["Belgrade", "Novi Sad", "Niš", "Kragujevac", "Subotica"],
    "Montenegro": ["Podgorica", "Nikšić", "Herceg Novi", "Pljevlja", "Budva"],
    "North Macedonia": ["Skopje", "Bitola", "Kumanovo", "Prilep", "Tetovo"],
    "Albania": ["Tirana", "Durrës", "Vlorë", "Shkodër", "Fier"],
    "Kosovo": ["Pristina", "Pristina District", "Gjilan", "Peja", "Prizren"],
    "Bosnia and Herzegovina": ["Sarajevo", "Banja Luka", "Tuzla", "Zenica", "Mostar"],
    "Croatia": ["Zagreb", "Split", "Rijeka", "Osijek", "Zadar"],
    "Slovenia": ["Ljubljana", "Maribor", "Celje", "Kranj", "Koper"],
    "Serbia": ["Belgrade", "Novi Sad", "Niš", "Kragujevac", "Subotica"],
    "Montenegro": ["Podgorica", "Nikšić", "Herceg Novi", "Pljevlja", "Budva"],
    "North Macedonia": ["Skopje", "Bitola", "Kumanovo", "Prilep", "Tetovo"],
    "Albania": ["Tirana", "Durrës", "Vlorë", "Shkodër", "Fier"],
    "Kosovo": ["Pristina", "Pristina District", "Gjilan", "Peja", "Prizren"],
    "Bosnia and Herzegovina": ["Sarajevo", "Banja Luka", "Tuzla", "Zenica", "Mostar"],
    "Croatia": ["Zagreb", "Split", "Rijeka", "Osijek", "Zadar"],
    "Slovenia": ["Ljubljana", "Maribor", "Celje", "Kranj", "Koper"],
    "Serbia": ["Belgrade", "Novi Sad", "Niš", "Kragujevac", "Subotica"],
}

# Initialize store data
store_data_batch = {
    "Name": [],
    "URL": [],
    "Location": []
}

# Generate 1000 stores
for country in additional_countries_cities:
    for city in additional_countries_cities[country]:
        for _ in range(34):  # Generate 34 stores for each city
            name, url = generate_store_name_url(city, country)
            location = f"{city}, {country}"
            
            store_data_batch["Name"].append(name)
            store_data_batch["URL"].append(url)
            store_data_batch["Location"].append(location)

# Convert to DataFrame
store_batch_df = pd.DataFrame(store_data_batch)

# Save to CSV
store_batch_df.to_csv("mock-stores.csv", index=False)

# Print first 5 rows
print(store_batch_df.head())

print("Done!")


