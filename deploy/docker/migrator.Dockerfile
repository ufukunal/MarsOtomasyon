# syntax=docker/dockerfile:1.7

FROM mcr.microsoft.com/dotnet/sdk:10.0.401-noble
WORKDIR /src

ENV NUGET_PACKAGES=/packages \
    DOTNET_CLI_HOME=/tmp

COPY Directory.Build.props ./
COPY src ./src

RUN dotnet tool install dotnet-ef --tool-path /tools --version 10.0.12
RUN dotnet restore src/Mars.Infrastructure/Mars.Infrastructure.csproj --packages "$NUGET_PACKAGES"
RUN dotnet build src/Mars.Infrastructure/Mars.Infrastructure.csproj \
    --configuration Release \
    --no-restore
RUN chmod -R a+rX /packages /tools /src

USER $APP_UID

ENTRYPOINT ["/tools/dotnet-ef"]
CMD ["--help"]
