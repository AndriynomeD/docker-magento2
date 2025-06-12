#
#--------------------------------------------------------------------------
# {{generated_by_builder}}
#--------------------------------------------------------------------------

FROM docker.elastic.co/elasticsearch/elasticsearch:<?= $ELASTICSEARCH_VERSION . PHP_EOL ?>

LABEL maintainer="AndriynomeD <andriynomed.work@gmail.com>"

RUN elasticsearch-plugin install analysis-phonetic
RUN elasticsearch-plugin install analysis-icu
